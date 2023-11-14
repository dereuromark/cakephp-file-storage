<?php
declare(strict_types = 1);

namespace FileStorage\Test\TestCase\Controller\Admin;

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
    public function testIndex(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
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
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * @return void
     */
    public function testAdd(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * @return void
     */
    public function testEdit(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * @return void
     */
    public function testDelete(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
