<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Controller;

use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\ServerRequest;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\Utility\Security;
use FileStorage\Controller\FileStorageController;
use FileStorage\Test\TestCase\FileStorageTestCase;
use FileStorage\Utility\SignedUrlGenerator;

/**
 * @uses \FileStorage\Controller\FileStorageController
 */
class FileStorageControllerTest extends FileStorageTestCase
{
    use IntegrationTestTrait;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->disableErrorHandlerMiddleware();
        // SignedUrlGenerator falls back to Security::getSalt() if no
        // FileStorage.signatureSecret is configured; the integration
        // test harness doesn't auto-load app config, so set one here.
        Security::setSalt('test-salt-for-file-storage-controller-tests-1234567890');
    }

    /**
     * Valid signature → 200 with the actual file body.
     *
     * @return void
     */
    public function testSignedDeliversFileForValidSignature(): void
    {
        $mock = $this->_createMockFile('Item/cake.icon.png');
        file_put_contents($mock, 'pretend-png-bytes');

        $entity = $this->FileStorage->get(1);
        $entity->adapter = 'Local';
        $entity->path = 'Item/cake.icon.png';
        $this->FileStorage->saveOrFail($entity);

        $signed = SignedUrlGenerator::generate($entity);

        $this->get([
            'plugin' => 'FileStorage',
            'prefix' => false,
            'controller' => 'FileStorage',
            'action' => 'signed',
            $entity->uuid,
            $signed['signature'],
        ]);

        $this->assertResponseOk();
        $this->assertContentType('image/png');
        // Body is served directly from disk via withFile() — assert presence of
        // our payload bytes. The exact assertion shape here covers both the
        // withFile() and withStringBody() code paths since both eventually
        // emit the same bytes.
        $this->assertResponseContains('pretend-png-bytes');
    }

    /**
     * Tampered signature → 403.
     *
     * @return void
     */
    public function testSignedRejectsTamperedSignature(): void
    {
        $this->_createMockFile('Item/cake.icon.png');

        $entity = $this->FileStorage->get(1);
        $entity->adapter = 'Local';
        $entity->path = 'Item/cake.icon.png';
        $this->FileStorage->saveOrFail($entity);

        $this->expectException(ForbiddenException::class);
        $this->get([
            'plugin' => 'FileStorage',
            'prefix' => false,
            'controller' => 'FileStorage',
            'action' => 'signed',
            $entity->uuid,
            str_repeat('0', 64),
        ]);
    }

    /**
     * Expired signature → 403 (same status as tampered, so probing callers
     * can't distinguish).
     *
     * @return void
     */
    public function testSignedRejectsExpiredSignature(): void
    {
        $this->_createMockFile('Item/cake.icon.png');

        $entity = $this->FileStorage->get(1);
        $entity->adapter = 'Local';
        $entity->path = 'Item/cake.icon.png';
        $this->FileStorage->saveOrFail($entity);

        $signed = SignedUrlGenerator::generate($entity, ['expires' => time() - 60]);

        $this->expectException(ForbiddenException::class);
        $this->get([
            'plugin' => 'FileStorage',
            'prefix' => false,
            'controller' => 'FileStorage',
            'action' => 'signed',
            $entity->uuid,
            $signed['signature'],
            '?' => ['expires' => $signed['expires']],
        ]);
    }

    /**
     * Unknown entity UUID → 404.
     *
     * @return void
     */
    public function testSignedRejectsUnknownId(): void
    {
        $this->expectException(NotFoundException::class);
        $this->get([
            'plugin' => 'FileStorage',
            'prefix' => false,
            'controller' => 'FileStorage',
            'action' => 'signed',
            99999,
            str_repeat('a', 64),
        ]);
    }

    /**
     * Missing signature path segment is caught by the route regex; the
     * controller's defence-in-depth check on null/empty also throws 400.
     *
     * @return void
     */
    public function testSignedRejectsEmptyArgsAsBadRequest(): void
    {
        $this->expectException(BadRequestException::class);

        // Invoke the action directly with null args to exercise the guard
        // — bypassing the router (the router regex would otherwise stop
        // the request from even reaching the action).
        $controller = new FileStorageController(
            new ServerRequest(),
        );
        $controller->signed();
    }
}
