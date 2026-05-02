<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Controller\Admin;

use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * @uses \FileStorage\Controller\Admin\FileStorageDashboardController
 */
class FileStorageDashboardControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.FileStorage.FileStorage',
    ];

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
    public function testIndexRendersStats(): void
    {
        $this->get(['controller' => 'FileStorageDashboard', 'action' => 'index', 'prefix' => 'Admin', 'plugin' => 'FileStorage']);

        $this->assertResponseOk();
        $this->assertSame(4, $this->viewVariable('totalCount'));
        $this->assertNotNull($this->viewVariable('byCollection'));
        $this->assertNotNull($this->viewVariable('byModel'));
        $this->assertNotNull($this->viewVariable('byAdapter'));
        $this->assertNotNull($this->viewVariable('recent'));
    }

    /**
     * @return void
     */
    public function testIndexForbiddenWhenAdminAccessUnset(): void
    {
        Configure::delete('FileStorage.adminAccess');

        $this->expectException(ForbiddenException::class);

        $this->get(['controller' => 'FileStorageDashboard', 'action' => 'index', 'prefix' => 'Admin', 'plugin' => 'FileStorage']);
    }
}
