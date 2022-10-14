<?php

declare(strict_types = 1);

namespace FileStorage\Test\TestCase\Model\Entity;

use FileStorage\Model\Entity\FileStorage;
use FileStorage\Test\TestCase\FileStorageTestCase;

class FileStorageTest extends FileStorageTestCase
{
    /**
     * @return void
     */
    public function testGetVariantUrl(): void
    {
        $fileStorage = new FileStorage();

        $result = $fileStorage->getVariantUrl('nonexistent');
        $this->assertNull($result);
    }

    /**
     * @return void
     */
    public function testGetVariantPath(): void
    {
        $fileStorage = new FileStorage();

        $result = $fileStorage->getVariantPath('nonexistent');
        $this->assertNull($result);
    }
}
