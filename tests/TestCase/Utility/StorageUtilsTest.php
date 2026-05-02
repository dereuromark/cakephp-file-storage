<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Utility;

use Cake\TestSuite\TestCase;
use FileStorage\Utility\StorageUtils;
use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;

class StorageUtilsTest extends TestCase
{
    /**
     * @return void
     */
    public function testFileToUploadedFileObjectThrowsOnMissingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not a readable file/');

        StorageUtils::fileToUploadedFileObject(TMP . 'this_file_does_not_exist_' . uniqid() . '.bin');
    }

    /**
     * @return void
     */
    public function testFileToUploadedFileArrayThrowsOnDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not a readable file/');

        StorageUtils::fileToUploadedFileArray(TMP);
    }

    /**
     * @return void
     */
    public function testFileToUploadedFileObject(): void
    {
        $testFile = TMP . 'test_storage_utils.txt';
        file_put_contents($testFile, 'test content');

        $uploadedFile = StorageUtils::fileToUploadedFileObject($testFile, 'text/plain');

        $this->assertInstanceOf(UploadedFileInterface::class, $uploadedFile);
        $this->assertSame(12, $uploadedFile->getSize());
        $this->assertSame(UPLOAD_ERR_OK, $uploadedFile->getError());
        $this->assertSame('test_storage_utils.txt', $uploadedFile->getClientFilename());
        $this->assertSame('text/plain', $uploadedFile->getClientMediaType());

        unlink($testFile);
    }

    /**
     * @return void
     */
    public function testFileToUploadedFileObjectWithoutMimeType(): void
    {
        $testFile = TMP . 'test_no_mime.txt';
        file_put_contents($testFile, 'data');

        $uploadedFile = StorageUtils::fileToUploadedFileObject($testFile);

        $this->assertInstanceOf(UploadedFileInterface::class, $uploadedFile);
        $this->assertSame(4, $uploadedFile->getSize());
        $this->assertNull($uploadedFile->getClientMediaType());

        unlink($testFile);
    }

    /**
     * @return void
     */
    public function testFileToUploadedFileArray(): void
    {
        $testFile = TMP . 'test_array.txt';
        file_put_contents($testFile, 'array test');

        $fileArray = StorageUtils::fileToUploadedFileArray($testFile, 'text/plain');

        $this->assertIsArray($fileArray);
        $this->assertSame($testFile, $fileArray['tmp_name']);
        $this->assertSame(10, $fileArray['size']);
        $this->assertSame(UPLOAD_ERR_OK, $fileArray['error']);
        $this->assertSame('test_array.txt', $fileArray['name']);
        $this->assertSame('text/plain', $fileArray['type']);

        unlink($testFile);
    }

    /**
     * @return void
     */
    public function testFileToUploadedFileArrayWithoutMimeType(): void
    {
        $testFile = TMP . 'test_array_no_mime.txt';
        file_put_contents($testFile, 'test');

        $fileArray = StorageUtils::fileToUploadedFileArray($testFile);

        $this->assertIsArray($fileArray);
        $this->assertNull($fileArray['type']);

        unlink($testFile);
    }
}
