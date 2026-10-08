<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Http;

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use FileStorage\Http\TusServer;
use FileStorage\Service\ResumableUploads;
use FileStorage\Test\TestCase\ResumableUploadTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;

/**
 * tus response and header parsing tests.
 *
 * @author Mark Scherer
 * @license MIT
 */
class TusServerTest extends ResumableUploadTestCase
{
    protected function request(string $method, ?string $id = null, array $headers = [], string $body = ''): ResponseInterface
    {
        $request = new ServerRequest(['url' => 'https://example.test/uploads' . ($id ? '/' . $id : ''), 'environment' => ['REQUEST_METHOD' => $method]]);
        foreach ($headers + ['Tus-Resumable' => '1.0.0'] as $key => $value) {
            $request = $request->withHeader($key, $value);
        }

        return (new TusServer($this->uploads))->handle($request->withBody($this->body($body)), $id);
    }

    public function testProtocolRoundTrip(): void
    {
        $response = $this->request('OPTIONS', headers: ['Tus-Resumable' => 'wrong']);
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('1.0.0', $response->getHeaderLine('Tus-Version'));
        $this->assertSame('creation,expiration,termination', $response->getHeaderLine('Tus-Extension'));
        $this->assertSame((string)(5 * 1024 ** 3), $response->getHeaderLine('Tus-Max-Size'));
        $response = $this->request('POST', headers: ['Upload-Length' => '3', 'Upload-Metadata' => $this->metadata()]);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertNotSame('', $response->getHeaderLine('Upload-Expires'));
        $id = basename($response->getHeaderLine('Location'));
        $response = $this->request('HEAD', $id);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('0', $response->getHeaderLine('Upload-Offset'));
        $this->assertSame('3', $response->getHeaderLine('Upload-Length'));
        $this->assertSame($this->metadata(), $response->getHeaderLine('Upload-Metadata'));
        $headers = ['Content-Type' => 'application/offset+octet-stream', 'Upload-Offset' => '0'];
        $response = $this->request('PATCH', $id, $headers, 'abc');
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('3', $response->getHeaderLine('Upload-Offset'));
        $this->assertSame(204, $this->request('PATCH', $id, ['Upload-Offset' => '3'] + $headers)->getStatusCode());
        $this->assertSame(200, $this->request('HEAD', $id)->getStatusCode());
        $this->assertSame(204, $this->request('DELETE', $id)->getStatusCode());
        $response = $this->request('HEAD', $id);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $this->assertSame('1.0.0', $response->getHeaderLine('Tus-Resumable'));
    }

    public static function numberProvider(): array
    {
        return [['0', 0], ['00001', 1], [(string)PHP_INT_MAX, PHP_INT_MAX], ['', null], ['-1', null], ['+1', null], ['1.0', null], [' 1', null], ["1\n", null], ['１', null], ['9223372036854775808', null], ['00000000000000000000', null]];
    }

    #[DataProvider('numberProvider')]
    public function testNumberParsing(string $input, ?int $expected): void
    {
        if ($expected === null) {
            $this->uploadStatus(400, static fn () => TusServer::number($input));

            return;
        }
        $this->assertSame($expected, TusServer::number($input));
    }

    public static function metadataProvider(): array
    {
        return [[null, []], ['', []], ['empty', ['empty' => '']], ['empty ', ['empty' => '']], [' a YQ== , b AA== ', ['a' => 'a', 'b' => "\0"]], ['a,a YQ==', null], ['a !!!', null], [',a', null], ['a  YQ==', null], ['model Li4=', null], ['filename Li4=', null], ['filename YS9i', null], ['model YS9i', null], ['collection Li4=', null], ['sha256 YQ==', null], ['filename AA==', null], ['filename /w==', null]];
    }

    #[DataProvider('metadataProvider')]
    public function testMetadataParsing(?string $input, ?array $expected): void
    {
        if ($expected === null) {
            $this->uploadStatus(400, static fn () => ResumableUploads::metadata($input));

            return;
        }
        $this->assertSame($expected, ResumableUploads::metadata($input));
    }

    public function testErrorsAndCheckOrder(): void
    {
        $row = $this->uploads->create(3, $this->metadata());
        foreach ([[['Upload-Offset' => '0'], '', 415], [['Content-Type' => 'application/offset+octet-stream'], '', 400], [['Content-Type' => 'application/offset+octet-stream', 'Upload-Offset' => '1'], '', 409], [['Content-Type' => 'application/offset+octet-stream', 'Upload-Offset' => '0'], 'abcd', 400]] as [$headers, $body, $status]) {
            $response = $this->request('PATCH', $row['id'], $headers, $body);
            $this->assertSame($status, $response->getStatusCode());
            $this->assertSame('1.0.0', $response->getHeaderLine('Tus-Resumable'));
            $this->assertNotSame('', $response->getHeaderLine('Upload-Expires'));
        }
        $this->now = $this->now->addSeconds(86401);
        $response = $this->request('HEAD', $row['id'], ['Tus-Resumable' => 'wrong']);
        $this->assertSame(412, $response->getStatusCode());
        $this->assertSame('', $response->getHeaderLine('Upload-Expires'));
        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        Configure::write('FileStorage.resumable.authorizer', static fn (): array => ['userId' => 'other']);
        $response = $this->request('HEAD', $row['id']);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('', $response->getHeaderLine('Upload-Expires'));
        Configure::write('FileStorage.resumable.authorizer', static fn (): array => ['userId' => '42']);
        foreach (['HEAD', 'PATCH', 'DELETE'] as $method) {
            $response = $this->request($method, $row['id']);
            $this->assertSame(410, $response->getStatusCode());
            $this->assertNotSame('', $response->getHeaderLine('Upload-Expires'));
        }
    }

    public function testConsumedResponses(): void
    {
        $row = $this->uploads->create(0, $this->metadata());
        $this->FileStorage->getConnection()->execute("UPDATE file_storage_uploads SET state = 'consumed'");
        unlink($this->part($row));
        $this->assertSame(200, $this->request('HEAD', $row['id'])->getStatusCode());
        $headers = ['Content-Type' => 'application/offset+octet-stream', 'Upload-Offset' => '0'];
        $this->assertSame(204, $this->request('PATCH', $row['id'], $headers)->getStatusCode());
        $this->assertSame(400, $this->request('PATCH', $row['id'], $headers, 'x')->getStatusCode());
        $this->assertSame(409, $this->request('PATCH', $row['id'], ['Upload-Offset' => '1'] + $headers)->getStatusCode());
        $this->assertSame(204, $this->request('DELETE', $row['id'])->getStatusCode());
    }

    public static function creationErrorProvider(): array
    {
        return [
            [[], null, 400],
            [['Upload-Length' => '0'], null, 400],
            [['Upload-Length' => '1', 'Upload-Metadata' => 'model SXRlbXM='], ['maxSize' => 0, 'authorizer' => null], 403],
            [['Upload-Length' => '3', 'Upload-Metadata' => 'model SXRlbXM='], ['maxSize' => 2], 413],
            [['Upload-Length' => '3', 'Upload-Metadata' => 'model SXRlbXM='], ['maxReservedBytes' => 2], 507],
        ];
    }

    #[DataProvider('creationErrorProvider')]
    public function testCreationErrors(array $headers, ?array $config, int $status): void
    {
        foreach ($config ?? [] as $key => $value) {
            Configure::write('FileStorage.resumable.' . $key, $value);
        }
        $response = $this->request('POST', headers: $headers);
        $this->assertSame($status, $response->getStatusCode());
        $this->assertSame('1.0.0', $response->getHeaderLine('Tus-Resumable'));
    }

    public function testLockedAndDigestErrorsIncludeExpiry(): void
    {
        $row = $this->uploads->create(3, $this->metadata(['sha256' => str_repeat('a', 64)]));
        $headers = ['Content-Type' => 'application/offset+octet-stream', 'Upload-Offset' => '0'];
        $handle = fopen($this->part($row), 'r+b');
        flock($handle, LOCK_EX);
        try {
            $response = $this->request('PATCH', $row['id'], $headers, 'abc');
            $this->assertSame(423, $response->getStatusCode());
            $this->assertNotSame('', $response->getHeaderLine('Upload-Expires'));
        } finally {
            fclose($handle);
        }
        $response = $this->request('PATCH', $row['id'], $headers, 'abc');
        $this->assertSame(422, $response->getStatusCode());
        $this->assertNotSame('', $response->getHeaderLine('Upload-Expires'));
    }

    public function testPatchUsesWriteAuthorizer(): void
    {
        $row = $this->uploads->create(3, $this->metadata());
        Configure::write('FileStorage.resumable.authorizer', static fn (string $action): array|false => $action === 'write' ? ['userId' => '42'] : false);
        $this->assertSame(204, $this->request('PATCH', $row['id'], ['Content-Type' => 'application/offset+octet-stream', 'Upload-Offset' => '0'], 'abc')->getStatusCode());
        $this->assertSame(403, $this->request('HEAD', $row['id'])->getStatusCode());
    }

    public function testUnsafePartIncludesExpiryWithoutReadingTarget(): void
    {
        $row = $this->uploads->create(3, $this->metadata());
        unlink($this->part($row));
        $target = $this->uploadPath . '/target';
        file_put_contents($target, 'secret');
        symlink($target, $this->part($row));
        $response = $this->request('HEAD', $row['id']);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertNotSame('', $response->getHeaderLine('Upload-Expires'));
        $this->assertSame('secret', file_get_contents($target));
    }
}
