<?php declare(strict_types=1);

namespace FileStorage\Service;

use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Text;
use FileStorage\Exception\BlobHashMismatchException;
use FileStorage\Exception\BlobNotAvailableException;
use InvalidArgumentException;
use League\Flysystem\Config;
use PhpCollective\Infrastructure\Storage\File;
use PhpCollective\Infrastructure\Storage\FileStorage as Storage;
use RuntimeException;
use Throwable;

/**
 * Registers content already stored on an adapter as a blob.
 *
 * @author Mark Scherer
 * @license MIT
 */
class BlobImporter
{
    protected Table $files;

    public function __construct(?Table $files = null)
    {
        $this->files = $files ?? TableRegistry::getTableLocator()->get('FileStorage.FileStorage');
    }

    /**
     * Disable verification only for hashes verified by storage, such as S3
     * ChecksumSHA256 on a single PUT. The filename supplies only the extension.
     *
     * @param string $adapter
     * @param string $sourcePath
     * @param string $filename
     * @param string|null $expectedHash
     * @param array{verify?: bool, deleteSource?: bool} $options
     *
     * @return \FileStorage\Service\BlobClaim
     */
    public function import(string $adapter, string $sourcePath, string $filename, ?string $expectedHash = null, array $options = []): BlobClaim
    {
        $verify = $options['verify'] ?? true;
        $root = trim(str_replace('\\', '/', (string)Configure::read('FileStorage.deduplicate.root', 'blobs')), '/');
        $normalizedSource = ltrim(str_replace('\\', '/', $sourcePath), '/');
        $normalizedSource = implode('/', array_filter(explode('/', $normalizedSource), static fn ($segment) => $segment !== '' && $segment !== '.'));
        if (
            $adapter === '' || ($expectedHash !== null && preg_match('/^[a-f0-9]{64}$/D', $expectedHash) !== 1)
            || (!$verify && $expectedHash === null) || Configure::read('FileStorage.hashAlgorithm', 'sha256') !== 'sha256'
        ) {
            throw new InvalidArgumentException('A non-empty adapter and a lowercase SHA-256 hash are required.');
        }
        if ($root === '' || preg_match('#(^|/)\.\.?(/|$)#', $root)) {
            throw new InvalidArgumentException('Invalid FileStorage.deduplicate.root.');
        }
        if (
            $normalizedSource === $root || str_starts_with($normalizedSource, $root . '/')
            || in_array('..', explode('/', $normalizedSource), true)
        ) {
            throw new InvalidArgumentException('Source must lie outside the blob root without parent segments.');
        }
        $storage = Configure::read('FileStorage.behaviorConfig.fileStorage');
        if (!$storage instanceof Storage) {
            throw new RuntimeException('FileStorage adapter service is not configured.');
        }
        $connection = $this->files->getConnection();
        // Nested transactions cannot provide an after-commit boundary for source deletion.
        if ($connection->inTransaction()) {
            throw new RuntimeException('Blob import requires a connection outside a transaction.');
        }
        $filesystem = $storage->getStorage($adapter);
        if (!$filesystem->fileExists($sourcePath)) {
            throw new BlobNotAvailableException('Source file is not available.');
        }
        $hash = $verify ? BlobStream::hash($filesystem, $sourcePath) : (string)$expectedHash;
        if ($verify && $expectedHash !== null && $hash !== $expectedHash) {
            throw new BlobHashMismatchException('Source hash mismatch.');
        }
        $registry = new BlobRegistry($this->files);
        $reused = false;
        $claim = $connection->transactional(function () use ($registry, $adapter, $hash, $storage, $filesystem, $filename, $sourcePath, $root, $verify, &$reused): BlobClaim {
            $claim = $registry->withLockWaitLimit(fn () => $registry->claim($adapter, $hash, DateTime::now()));
            $destination = $claim->path;
            if ($destination === null) {
                $destination = $storage->buildPath(File::create($filename, 0, '', $adapter)->withUuid(Text::uuid())->withHash($hash))->path();
                if (BlobPath::check($destination, $root, $hash, true) !== null) {
                    throw new InvalidArgumentException('hashPathTemplate must name the file by its hash under the blob root, outside .tmp.');
                }
            } elseif (BlobPath::check($destination, $root, $hash, true) !== null) {
                throw new RuntimeException('Registered blob path is invalid.');
            }
            $createdDestination = false;
            try {
                if ($filesystem->fileExists($destination)) {
                    // A trusted source hash says nothing about an unregistered leftover at the destination.
                    if (($verify || $claim->path === null) && BlobStream::hash($filesystem, $destination) !== $hash) {
                        throw new RuntimeException($claim->path === null ? 'destination occupied' : 'registered blob has wrong content');
                    }
                    $reused = true;
                } else {
                    $createdDestination = true;
                    $filesystem->copy($sourcePath, $destination, new Config());
                    if ($verify && BlobStream::hash($filesystem, $destination) !== $hash) {
                        throw new BlobHashMismatchException('Destination read-back mismatch.');
                    }
                }
                if ($claim->path === null) {
                    $registry->recordPath($claim->id, $destination);
                }

                return new BlobClaim($claim->id, $destination, $hash);
            } catch (Throwable $exception) {
                if ($createdDestination) {
                    try {
                        $filesystem->delete($destination);
                    } catch (Throwable $cleanup) {
                        Log::warning('Could not remove failed blob import destination: ' . $cleanup->getMessage());
                    }
                }

                throw $exception;
            }
        });
        if ($options['deleteSource'] ?? false) {
            try {
                $filesystem->delete($sourcePath);
            } catch (Throwable $exception) {
                Log::warning('Could not delete imported blob source: ' . $exception->getMessage());
            }
        }
        try {
            $this->files->dispatchEvent('FileStorage.blobImported', [
                'adapter' => $adapter,
                'hash' => $hash,
                'path' => $claim->path,
                'sourcePath' => $sourcePath,
                'reused' => $reused,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Blob import listener failed: ' . $exception->getMessage());
        }

        return $claim;
    }
}
