<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Service;

use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Driver;
use Cake\Database\Driver\Sqlite;
use Cake\Database\Schema\CollectionInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Table;
use FileStorage\Exception\UploadInTransactionException;
use FileStorage\Exception\UploadInvalidException;
use FileStorage\Service\CleanupService;
use FileStorage\Service\ResumableUploads;
use FileStorage\Test\TestCase\ResumableUploadTestCase;
use Laminas\Diactoros\Stream;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use stdClass;
use TestApp\Stream\UploadPartStream;

/**
 * Durable upload and consumption tests.
 *
 * @author Mark Scherer
 * @license MIT
 */
class ResumableUploadsTest extends ResumableUploadTestCase
{
    public static function chunksProvider(): array
    {
        return [[64], [79], [65537]];
    }

    #[DataProvider('chunksProvider')]
    public function testBinaryChunks(int $chunkSize): void
    {
        $bytes = random_bytes($chunkSize === 65537 ? 3 * 1024 * 1024 + 17 : 1027);
        $row = $this->uploads->create(strlen($bytes), $this->metadata(['sha256' => hash('sha256', $bytes)]));
        $events = [];
        $this->FileStorage->getEventManager()->on('FileStorage.uploadCompleted', function (EventInterface $event) use (&$events): void {
            $events[] = $event->getData('upload');
        });
        $offset = 0;
        foreach (str_split($bytes, $chunkSize) as $chunk) {
            $row = $this->uploads->append($row['id'], $offset, $this->body($chunk));
            $offset += strlen($chunk);
            $this->assertSame($offset, (int)$this->uploads->offset($row['id'])['upload_offset']);
        }
        $this->assertSame('complete', $row['state']);
        $this->assertSame(hash_file('sha256', $this->part($row)), $row['hash']);
        $this->assertSame(1, count($events));
        $this->assertSame($bytes, file_get_contents($this->part($row)));
    }

    public function testFailingCompletionListenerDoesNotFailTheChunk(): void
    {
        $row = $this->uploads->create(3, $this->metadata());
        $this->FileStorage->getEventManager()->on('FileStorage.uploadCompleted', function (): void {
            throw new RuntimeException('listener failed');
        });

        $row = $this->uploads->append($row['id'], 0, $this->body('abc'));

        $this->assertSame('complete', $row['state']);
        $this->assertSame('complete', $this->uploads->offset($row['id'])['state']);
    }

    public function testEmptyPatchIncrementsRevision(): void
    {
        $row = $this->uploads->create(3, $this->metadata());
        $updated = $this->uploads->append($row['id'], 0, $this->body(''));
        $this->assertSame(1, $updated['revision']);
        $updated = $this->uploads->append($row['id'], 0, $this->body('abc'));
        $updated = $this->uploads->append($row['id'], 3, $this->body(''));
        $this->assertSame(3, $updated['revision']);
    }

    public function testZeroLengthAndDeclaredHash(): void
    {
        foreach ([[], ['sha256' => hash('sha256', '')]] as $extra) {
            $row = $this->uploads->create(0, $this->metadata($extra));
            $this->assertSame('complete', $row['state']);
            $this->assertSame(hash('sha256', ''), $row['hash']);
        }
        $this->uploadStatus(422, fn () => $this->uploads->create(0, $this->metadata(['sha256' => str_repeat('a', 64)])));
        $this->assertSame(2, (int)$this->FileStorage->getConnection()->execute('SELECT COUNT(*) FROM file_storage_uploads')->fetchColumn(0));
    }

    public function testOffsetsOverflowAndRecovery(): void
    {
        $row = $this->uploads->create(6, $this->metadata());
        $this->uploadStatus(409, fn () => $this->uploads->append($row['id'], 1, $this->body('abc')));
        $this->uploads->append($row['id'], 0, $this->body('abc'));
        file_put_contents($this->part($row), 'discard', FILE_APPEND);
        $this->uploadStatus(400, fn () => $this->uploads->append($row['id'], 3, $this->body('toolong')));
        $this->assertSame(3, (int)$this->uploads->offset($row['id'])['upload_offset']);
        $row = $this->uploads->append($row['id'], 3, $this->body('def'));
        $this->assertSame('abcdef', file_get_contents($this->part($row)));
    }

    public function testShortPartAndCorruptHashEndSession(): void
    {
        $row = $this->uploads->create(6, $this->metadata());
        $this->uploads->append($row['id'], 0, $this->body('abc'));
        file_put_contents($this->part($row), 'a');
        $this->uploadStatus(410, fn () => $this->uploads->append($row['id'], 3, $this->body('def')));
        $this->uploadStatus(404, fn () => $this->uploads->offset($row['id']));
        $row = $this->uploads->create(6, $this->metadata());
        $this->FileStorage->getConnection()->execute('UPDATE file_storage_uploads SET hash_state = :bad WHERE id = :id', ['bad' => base64_encode(serialize(new stdClass())), 'id' => $row['id']]);
        $this->uploadStatus(410, fn () => $this->uploads->append($row['id'], 0, $this->body('abc')));
        $this->assertFileDoesNotExist($this->part($row));
    }

    public function testMismatch(): void
    {
        $row = $this->uploads->create(3, $this->metadata(['sha256' => str_repeat('a', 64)]));
        $this->uploadStatus(422, fn () => $this->uploads->append($row['id'], 0, $this->body('abc')));
        $this->assertFileDoesNotExist($this->part($row));
        $this->uploadStatus(404, fn () => $this->uploads->offset($row['id']));
    }

    public function testExpiryAndOwnerCheckOrder(): void
    {
        Configure::write('FileStorage.resumable.expires', 10);
        Configure::write('FileStorage.resumable.completedExpires', 30);
        $row = $this->uploads->create(3, $this->metadata());
        $this->now = $this->now->addSeconds(9);
        $row = $this->uploads->append($row['id'], 0, $this->body('abc'));
        $this->assertSame($this->now->getTimestamp() + 30, $row['expires']->getTimestamp());
        $this->now = $this->now->addSeconds(31);
        $this->uploadStatus(403, fn () => $this->uploads->offset($row['id'], ['userId' => 'other']));
        foreach (['offset', 'terminate'] as $method) {
            $error = $this->uploadStatus(410, fn () => $this->uploads->$method($row['id']));
            $this->assertSame($row['id'], $error->upload['id']);
        }
    }

    public static function quotaProvider(): array
    {
        return [['maxSize', 2, 413], ['maxSessions', 0, 403], ['maxBytesPerOwner', 2, 403], ['maxReservedBytes', 2, 507], ['minFreeBytes', PHP_INT_MAX, 507]];
    }

    #[DataProvider('quotaProvider')]
    public function testQuotas(string $key, int $limit, int $status): void
    {
        Configure::write('FileStorage.resumable.' . $key, $limit);
        $this->uploadStatus($status, fn () => $this->uploads->create(3, $this->metadata()));
    }

    public function testReservationSurvivesCompletion(): void
    {
        Configure::write('FileStorage.resumable.maxReservedBytes', 3);
        $row = $this->uploads->create(3, $this->metadata());
        $this->uploadStatus(507, fn () => $this->uploads->create(1, $this->metadata()));
        $this->uploads->append($row['id'], 0, $this->body('abc'));
        $this->uploadStatus(507, fn () => $this->uploads->create(1, $this->metadata()));
        $this->uploads->terminate($row['id']);
        $this->assertSame(1, $this->uploads->create(1, $this->metadata())['size']);
    }

    public function testLocksAndCleanup(): void
    {
        $row = $this->uploads->create(3, $this->metadata());
        $handle = fopen($this->part($row), 'r+b');
        flock($handle, LOCK_EX);
        try {
            $this->uploadStatus(423, fn () => $this->uploads->append($row['id'], 0, $this->body('abc')));
            $this->uploadStatus(423, fn () => $this->uploads->terminate($row['id']));
            $this->uploadStatus(423, fn () => $this->uploads->consume($row['id'], ['foreign_key' => 1]));
            $this->FileStorage->getConnection()->execute('UPDATE file_storage_uploads SET expires = :date', ['date' => $this->now->subSeconds(1)], ['date' => 'datetime']);
            $this->assertSame(1, $this->uploads->cleanup()['skippedUploads']);
        } finally {
            fclose($handle);
        }
        $this->assertSame(['deletedUploads' => 1, 'deletedUploadParts' => 1, 'skippedUploads' => 0], $this->uploads->cleanup());
    }

    public function testMissingPartKeepsRowAndConsumedResponses(): void
    {
        $row = $this->uploads->create(3, $this->metadata());
        unlink($this->part($row));
        $this->uploadStatus(410, fn () => $this->uploads->append($row['id'], 0, $this->body('abc')));
        $this->assertSame(1, (int)$this->FileStorage->getConnection()->execute('SELECT COUNT(*) FROM file_storage_uploads')->fetchColumn(0));
        $this->FileStorage->getConnection()->execute("UPDATE file_storage_uploads SET state = 'consumed', upload_offset = size");
        $this->assertSame(3, (int)$this->uploads->offset($row['id'])['upload_offset']);
        $this->uploads->append($row['id'], 3, $this->body(''));
        $this->uploadStatus(400, fn () => $this->uploads->append($row['id'], 3, $this->body('x')));
        $this->uploadStatus(409, fn () => $this->uploads->append($row['id'], 0, $this->body('')));
        file_put_contents($this->part($row), 'leftover');
        $this->uploads->terminate($row['id']);
        $this->assertFileDoesNotExist($this->part($row));
        $this->uploadStatus(404, fn () => $this->uploads->offset($row['id']));
    }

    public function testTransactionRefusal(): void
    {
        $row = $this->uploads->create(3, $this->metadata());
        $connection = $this->FileStorage->getConnection();
        $connection->begin();
        try {
            foreach ([fn () => $this->uploads->create(3, $this->metadata()), fn () => $this->uploads->append($row['id'], 0, $this->body('')), fn () => $this->uploads->terminate($row['id']), fn () => $this->uploads->consume($row['id'], []), fn () => $this->uploads->cleanup()] as $call) {
                $this->assertInstanceOf(UploadInTransactionException::class, $this->uploadStatus(500, $call));
            }
        } finally {
            $connection->rollback();
        }
    }

    public static function dedupProvider(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('dedupProvider')]
    public function testConsumeAndDeduplicate(bool $dedup): void
    {
        Configure::write('FileStorage.deduplicate.collections', ['Items' => ['Files' => $dedup]]);
        $files = [];
        for ($i = 0; $i < 2; $i++) {
            $row = $this->uploads->create(3, $this->metadata());
            $this->uploads->append($row['id'], 0, $this->body('abc'));
            $files[] = $this->uploads->consume($row['id'], ['foreign_key' => 123, 'user_id' => 42]);
            $this->assertFileDoesNotExist($this->part($row));
            $this->assertSame('consumed', $this->uploads->offset($row['id'])['state']);
            $this->assertSame(hash('sha256', 'abc'), $files[$i]->get('hash'));
            $this->uploadStatus(409, fn () => $this->uploads->consume($row['id'], ['foreign_key' => 123]));
        }
        if ($dedup) {
            $this->assertSame($files[0]->get('blob_id'), $files[1]->get('blob_id'));
        }
    }

    public function testConsumeReservedAndValidationFailure(): void
    {
        $row = $this->uploads->create(3, $this->metadata());
        $this->uploads->append($row['id'], 0, $this->body('abc'));
        $this->uploadStatus(400, fn () => $this->uploads->consume($row['id'], ['path' => 'bad']));
        $this->FileStorage->getValidator()->add('foreign_key', 'reject', ['rule' => static fn (): bool => false]);
        $error = $this->uploadStatus(400, fn () => $this->uploads->consume($row['id'], ['foreign_key' => 1]));
        $this->assertInstanceOf(UploadInvalidException::class, $error);
        $this->assertNotNull($error->entity);
        $this->assertSame('complete', $this->uploads->offset($row['id'])['state']);
        $this->assertFileExists($this->part($row));
    }

    public function testCleanupMissingConsumedAndOrphan(): void
    {
        $rows = [];
        foreach ([0, 3, 3] as $size) {
            $rows[] = $this->uploads->create($size, $this->metadata());
        }
        unlink($this->part($rows[1]));
        $this->FileStorage->getConnection()->execute("UPDATE file_storage_uploads SET state = 'consumed' WHERE id = :id", ['id' => $rows[2]['id']]);
        $orphan = $this->uploadPath . '/11111111-1111-1111-1111-111111111111.part';
        touch($orphan, $this->now->getTimestamp() - 90000);
        $this->now = $this->now->addSeconds(86401);
        $this->assertSame(['deletedUploads' => 3, 'deletedUploadParts' => 3, 'skippedUploads' => 0], $this->uploads->cleanup(true));
        $this->assertSame(['deletedUploads' => 3, 'deletedUploadParts' => 3, 'skippedUploads' => 0], $this->uploads->cleanup());
    }

    public function testDifferentConnectionsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        new ResumableUploads($this->FileStorage, new Table(['connection' => new Connection(['driver' => Sqlite::class, 'database' => ':memory:'])]));
    }

    public function testShortWritesAndFailedWrites(): void
    {
        $row = $this->uploads->create(6, $this->metadata());
        UploadPartStream::$path = $this->part($row);
        UploadPartStream::$failWrite = false;
        stream_wrapper_register('uploadfault', UploadPartStream::class);
        try {
            $service = new class ($this->FileStorage) extends ResumableUploads {
                protected function openPart(string $path): mixed
                {
                    return fopen('uploadfault://part', 'r+b');
                }

                protected function syncPart(mixed $handle): bool
                {
                    return fflush($handle) && fsync(UploadPartStream::$native);
                }
            };
            $service->append($row['id'], 0, $this->body('abc'));
            UploadPartStream::$failWrite = true;
            $this->uploadStatus(500, fn () => $service->append($row['id'], 3, $this->body('def')));
            $this->assertSame(3, (int)$service->offset($row['id'])['upload_offset']);
            UploadPartStream::$failWrite = false;
            $row = $service->append($row['id'], 3, $this->body('def'));
            $this->assertSame(hash('sha256', 'abcdef'), $row['hash']);
        } finally {
            stream_wrapper_unregister('uploadfault');
        }
    }

    public function testFailedTruncateAndSyncKeepCommittedOffset(): void
    {
        $row = $this->uploads->create(6, $this->metadata());
        $this->uploads->append($row['id'], 0, $this->body('abc'));
        file_put_contents($this->part($row), 'extra', FILE_APPEND);
        $service = $this->getMockBuilder(ResumableUploads::class)->setConstructorArgs([$this->FileStorage])->onlyMethods(['truncatePart'])->getMock();
        $service->expects($this->once())->method('truncatePart')->willReturn(false);
        $this->uploadStatus(500, fn () => $service->append($row['id'], 3, $this->body('def')));
        $this->assertSame('abcextra', file_get_contents($this->part($row)));
        $service = $this->getMockBuilder(ResumableUploads::class)->setConstructorArgs([$this->FileStorage])->onlyMethods(['syncPart'])->getMock();
        $service->expects($this->once())->method('syncPart')->willReturn(false);
        $this->uploadStatus(500, fn () => $service->append($row['id'], 3, $this->body('def')));
        $this->assertSame(3, (int)$this->uploads->offset($row['id'])['upload_offset']);
        $row = $this->uploads->append($row['id'], 3, $this->body('def'));
        $this->assertSame(hash('sha256', 'abcdef'), $row['hash']);
    }

    public function testFailedUnlinkKeepsReservation(): void
    {
        $row = $this->uploads->create(0, $this->metadata());
        $service = $this->getMockBuilder(ResumableUploads::class)->setConstructorArgs([$this->FileStorage, null, fn () => $this->now])->onlyMethods(['unlinkPart'])->getMock();
        $service->expects($this->exactly(2))->method('unlinkPart')->willReturn(false);
        $this->uploadStatus(500, fn () => $service->terminate($row['id']));
        $this->now = $this->now->addSeconds(86401);
        $this->assertSame(1, $service->cleanup()['skippedUploads']);
        $this->assertSame(1, (int)$this->FileStorage->getConnection()->execute('SELECT COUNT(*) FROM file_storage_uploads')->fetchColumn(0));
        $this->assertFileExists($this->part($row));
    }

    public function testFreshReadUnderLock(): void
    {
        $row = $this->uploads->create(6, $this->metadata());
        $service = new class ($this->FileStorage) extends ResumableUploads {
            public ResumableUploads $writer;

            protected function openPart(string $path): mixed
            {
                $id = basename($path, '.part');
                $this->writer->append($id, 0, fopen('data://text/plain,abc', 'r'));

                return parent::openPart($path);
            }
        };
        $service->writer = $this->uploads;
        $this->uploadStatus(409, fn () => $service->append($row['id'], 0, $this->body('def')));
        $this->assertSame(3, (int)$this->uploads->offset($row['id'])['upload_offset']);
    }

    public function testRaceWithConsumeUsesConsumedRow(): void
    {
        $row = $this->uploads->create(3, $this->metadata());
        $this->uploads->append($row['id'], 0, $this->body('abc'));
        $service = new class ($this->FileStorage) extends ResumableUploads {
            public ResumableUploads $consumer;

            protected function openPart(string $path): mixed
            {
                $this->consumer->consume(basename($path, '.part'), ['foreign_key' => 1]);

                return parent::openPart($path);
            }
        };
        $service->consumer = $this->uploads;
        $this->assertSame('consumed', $service->append($row['id'], 3, $this->body(''))['state']);
    }

    public function testCleanupRechecksRenewedExpiry(): void
    {
        $row = $this->uploads->create(0, $this->metadata());
        $this->now = $this->now->addSeconds(86401);
        $service = new class ($this->FileStorage, null, fn () => $this->now) extends ResumableUploads {
            protected function openPart(string $path): mixed
            {
                $id = basename($path, '.part');
                $row = $this->row($id);
                $this->update($row, ['state' => 'consumed', 'expires' => $this->deadline(true)]);

                return parent::openPart($path);
            }
        };
        $this->assertSame(['deletedUploads' => 0, 'deletedUploadParts' => 0, 'skippedUploads' => 1], $service->cleanup());
        $this->assertFileExists($this->part($row));
    }

    public function testFailedSaveKeepsCompleteSession(): void
    {
        $row = $this->uploads->create(3, $this->metadata());
        $this->uploads->append($row['id'], 0, $this->body('abc'));
        $this->FileStorage->getEventManager()->on('Model.beforeSave', static function (EventInterface $event): void {
            $event->stopPropagation();

            $event->setResult(false);
        });
        $error = $this->uploadStatus(400, fn () => $this->uploads->consume($row['id'], ['foreign_key' => 1]));
        $this->assertNotNull($error->entity);
        $this->assertSame('complete', $this->uploads->offset($row['id'])['state']);
        $this->assertFileExists($this->part($row));
    }

    public static function authorizerProvider(): array
    {
        return [[null], [false], ['strlen'], [true], [42], [[]], [['userId' => 42]], [['userId' => '']], [['userId' => str_repeat('x', 37)]]];
    }

    #[DataProvider('authorizerProvider')]
    public function testAuthorizerReturnContract(mixed $result): void
    {
        Configure::write('FileStorage.resumable.authorizer', $result === 'strlen' ? 'strlen' : static fn () => $result);
        $this->uploadStatus(403, fn () => $this->uploads->create(3, $this->metadata()));
    }

    public function testConsumeAuthorizerReceivesDataAndCallerContext(): void
    {
        $row = $this->uploads->create(3, $this->metadata());
        $this->uploads->append($row['id'], 0, $this->body('abc'));
        $calls = [];
        Configure::write('FileStorage.resumable.authorizer', static function (string $action, array $upload, array $context) use (&$calls): array {
            $calls[] = [$action, $upload, $context];

            return ['userId' => '42'];
        });
        $data = ['foreign_key' => 11];
        $context = ['userId' => '42', 'application' => 'context'];
        $this->uploads->consume($row['id'], $data, $context);
        $this->assertSame('consume', $calls[0][0]);
        $this->assertSame('42', $calls[0][1]['owner']);
        $this->assertSame($data, $calls[0][1]['data']);
        $this->assertSame($context, $calls[0][2]);
    }

    public function testCompletedDigestHandoffForHashConfiguration(): void
    {
        foreach (['sha256', 'sha1', false] as $algorithm) {
            Configure::write('FileStorage.hashAlgorithm', $algorithm);
            $row = $this->uploads->create(3, $this->metadata());
            $this->uploads->append($row['id'], 0, $this->body('abc'));
            $entity = $this->uploads->consume($row['id'], ['foreign_key' => 1]);
            $this->assertSame($algorithm === false ? null : hash($algorithm, 'abc'), $entity->get('hash'));
        }
        Configure::delete('FileStorage.hashAlgorithm');
    }

    public function testRevisionMismatchCannotCommitBytes(): void
    {
        $row = $this->uploads->create(3, $this->metadata());
        $stream = new class ('php://temp', 'w+b') extends Stream {
            public Connection $connection;

            public function read(int $length): string
            {
                $this->connection->execute('UPDATE file_storage_uploads SET revision = revision + 1');

                return parent::read($length);
            }
        };
        $stream->connection = $this->FileStorage->getConnection();
        $stream->write('abc');
        $stream->rewind();
        $this->uploadStatus(409, fn () => $this->uploads->append($row['id'], 0, $stream));
        $this->assertSame(0, (int)$this->uploads->offset($row['id'])['upload_offset']);
    }

    public function testCleanupServiceReport(): void
    {
        $row = $this->uploads->create(0, $this->metadata());
        $this->FileStorage->getConnection()->execute('UPDATE file_storage_uploads SET expires = :date', ['date' => $this->now->subSeconds(1)], ['date' => 'datetime']);
        $service = new CleanupService();
        $preview = $service->runUploads(true);
        $this->assertSame(1, $preview->deletedUploads);
        $this->assertSame(1, $preview->deletedUploadParts);
        $this->assertFileExists($this->part($row));
        $report = $service->runUploads(false);
        $this->assertSame(1, $report->deletedUploads);
        $this->assertSame(1, $report->deletedUploadParts);
        $this->assertSame(0, $report->skippedUploads);
        $this->assertFileDoesNotExist($this->part($row));
    }

    #[DataProvider('cleanupPreconditionProvider')]
    public function testCleanupSkipsUploadsWithoutSupport(bool $unsupported): void
    {
        $table = $this->getTableLocator()->get('FileStorage.FileStorage');
        $original = $table->getConnection();
        $connection = $this->createStub(Connection::class);
        $connection->method('getDriver')->willReturn($this->createStub($unsupported ? Driver::class : Sqlite::class));
        $schema = $this->createStub(CollectionInterface::class);
        $schema->method('listTables')->willReturn([]);
        $connection->method('getSchemaCollection')->willReturn($schema);
        $table->setConnection($connection);
        try {
            $report = (new CleanupService())->runUploads(true);
        } finally {
            $table->setConnection($original);
        }
        $this->assertSame(0, $report->deletedUploads);
        $this->assertSame($unsupported ? [] : ['Table `file_storage_uploads` is missing, skipping upload cleanup. Run the plugin migrations.'], $report->warnings);
    }

    public static function cleanupPreconditionProvider(): array
    {
        return [[true], [false]];
    }
}
