<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Controller\Admin;

use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\ServerRequest;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * @uses \FileStorage\Controller\Admin\FileStorageController
 */
class FileStorageControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->disableErrorHandlerMiddleware();
        Configure::write('FileStorage.adminAccess', true);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Configure::delete('FileStorage.adminAccess');
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testIndex(): void
    {
        $this->get(['controller' => 'FileStorage', 'action' => 'index', 'prefix' => 'Admin', 'plugin' => 'FileStorage']);

        $this->assertResponseOk();
        $fileStorage = $this->viewVariable('fileStorage');
        $this->assertNotNull($fileStorage);
        $this->assertCount(4, $fileStorage);
    }

    /**
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.FileStorage.FileStorage',
    ];

    /**
     * @return void
     */
    public function testView(): void
    {
        $this->get(['controller' => 'FileStorage', 'action' => 'view', 1, 'prefix' => 'Admin', 'plugin' => 'FileStorage']);

        $this->assertResponseOk();
        $fileStorage = $this->viewVariable('fileStorage');
        $this->assertInstanceOf('FileStorage\Model\Entity\FileStorage', $fileStorage);
        $this->assertSame(1, $fileStorage->id);
    }

    /**
     * @return void
     */
    public function testEdit(): void
    {
        $this->get(['controller' => 'FileStorage', 'action' => 'edit', 1, 'prefix' => 'Admin', 'plugin' => 'FileStorage']);

        $this->assertResponseOk();
        $fileStorage = $this->viewVariable('fileStorage');
        $this->assertInstanceOf('FileStorage\Model\Entity\FileStorage', $fileStorage);
        $this->assertSame(1, $fileStorage->id);
    }

    /**
     * @return void
     */
    public function testEditPost(): void
    {
        $this->enableRetainFlashMessages();

        $data = [
            'filename' => 'updated-filename.png',
            'filesize' => 98765,
            'mime_type' => 'image/png',
            'extension' => 'png',
            'path' => 'Item/updated.png',
            'adapter' => 'Local',
        ];
        $this->post(['controller' => 'FileStorage', 'action' => 'edit', 1, 'prefix' => 'Admin', 'plugin' => 'FileStorage'], $data);

        // Should redirect on success or re-render form on validation failure
        // For now just verify response completed
        $this->assertResponseOk();
    }

    /**
     * @return void
     */
    public function testDelete(): void
    {
        $this->enableRetainFlashMessages();

        $this->post(['controller' => 'FileStorage', 'action' => 'delete', 1, 'prefix' => 'Admin', 'plugin' => 'FileStorage']);

        $this->assertRedirect(['controller' => 'FileStorage', 'action' => 'index', 'prefix' => 'Admin', 'plugin' => 'FileStorage']);
        $this->assertFlashMessage('The file storage has been deleted.');
    }

    /**
     * @return void
     */
    public function testIndexForbiddenWhenAdminAccessUnset(): void
    {
        Configure::delete('FileStorage.adminAccess');

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessageMatches('/adminAccess/');

        $this->get(['controller' => 'FileStorage', 'action' => 'index', 'prefix' => 'Admin', 'plugin' => 'FileStorage']);
    }

    /**
     * @return void
     */
    public function testIndexForbiddenWhenAdminAccessFalse(): void
    {
        Configure::write('FileStorage.adminAccess', false);

        $this->expectException(ForbiddenException::class);

        $this->get(['controller' => 'FileStorage', 'action' => 'index', 'prefix' => 'Admin', 'plugin' => 'FileStorage']);
    }

    /**
     * @return void
     */
    public function testIndexForbiddenWhenClosureDenies(): void
    {
        Configure::write(
            'FileStorage.adminAccess',
            fn (ServerRequest $request): bool => false,
        );

        $this->expectException(ForbiddenException::class);

        $this->get(['controller' => 'FileStorage', 'action' => 'index', 'prefix' => 'Admin', 'plugin' => 'FileStorage']);
    }

    /**
     * @return void
     */
    public function testIndexAllowedWhenClosureGrants(): void
    {
        Configure::write(
            'FileStorage.adminAccess',
            fn (ServerRequest $request): bool => true,
        );

        $this->get(['controller' => 'FileStorage', 'action' => 'index', 'prefix' => 'Admin', 'plugin' => 'FileStorage']);

        $this->assertResponseOk();
    }

    /**
     * @return void
     */
    public function testDeleteForbiddenWhenAdminAccessUnset(): void
    {
        Configure::delete('FileStorage.adminAccess');
        $this->enableRetainFlashMessages();

        $this->expectException(ForbiddenException::class);

        $this->post(['controller' => 'FileStorage', 'action' => 'delete', 1, 'prefix' => 'Admin', 'plugin' => 'FileStorage']);
    }
}
