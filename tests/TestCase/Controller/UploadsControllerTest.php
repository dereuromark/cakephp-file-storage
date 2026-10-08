<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Controller;

use Cake\Core\Configure;
use Cake\Http\Exception\InvalidCsrfTokenException;
use Cake\Http\Response;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\Utility\Security;
use FileStorage\Test\TestCase\ResumableUploadTestCase;
use TestApp\UploadsApplication;
use Throwable;

/**
 * Application-routed tus endpoints.
 *
 * @uses \FileStorage\Controller\UploadsController
 * @author Mark Scherer
 * @license MIT
 */
class UploadsControllerTest extends ResumableUploadTestCase
{
    use IntegrationTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->configApplication(UploadsApplication::class, [CONFIG]);
    }

    protected function _handleError(Throwable $exception): void
    {
        if ($exception instanceof InvalidCsrfTokenException) {
            $this->_response = (new Response())->withStatus(403);

            return;
        }

        throw $exception;
    }

    public function testRoutedRoundTrip(): void
    {
        $this->configRequest(['headers' => ['Tus-Resumable' => '1.0.0', 'Upload-Length' => '3', 'Upload-Metadata' => $this->metadata()]]);
        $this->post('/file-storage/uploads');
        $this->assertResponseCode(201);
        $location = $this->_response->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('#^/file-storage/uploads/[0-9a-f-]{36}$#', $location);
        $this->configRequest(['headers' => ['Tus-Resumable' => '1.0.0', 'Content-Type' => 'application/offset+octet-stream', 'Upload-Offset' => '0']]);
        $this->patch($location, 'abc');
        $this->assertResponseCode(204);
        $this->assertHeader('Upload-Offset', '3');
        $this->configRequest(['headers' => ['Tus-Resumable' => '1.0.0']]);
        $this->head($location);
        $this->assertResponseCode(200);
        $this->assertHeader('Upload-Length', '3');
        $this->assertHeader('Cache-Control', 'no-store');
        $this->configRequest(['headers' => ['Tus-Resumable' => '1.0.0']]);
        $this->delete($location);
        $this->assertResponseCode(204);
    }

    public function testNoAuthorizer(): void
    {
        Configure::delete('FileStorage.resumable.authorizer');
        $this->configRequest(['headers' => ['Tus-Resumable' => '1.0.0', 'Upload-Length' => '3', 'Upload-Metadata' => $this->metadata()]]);
        $this->post('/file-storage/uploads');
        $this->assertResponseCode(403);
        $this->assertHeader('Tus-Resumable', '1.0.0');
    }

    public function testCsrfIsEnforcedByApplication(): void
    {
        Security::setSalt('upload-controller-test-salt-1234567890');
        Configure::write('Test.uploadCsrf', true);
        try {
            $headers = ['Tus-Resumable' => '1.0.0', 'Upload-Length' => '3', 'Upload-Metadata' => $this->metadata()];
            $this->configRequest(['headers' => $headers]);
            $this->post('/file-storage/uploads');
            $this->assertResponseCode(403);
            $this->enableCsrfToken();
            $this->configRequest(['headers' => $headers]);
            $this->post('/file-storage/uploads');
            $this->assertResponseCode(201);
        } finally {
            Configure::delete('Test.uploadCsrf');
        }
    }
}
