<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Model\Validation;

use Cake\Core\Plugin;
use Cake\TestSuite\TestCase;
use FileStorage\Model\Validation\ImageValidationTrait;
use Laminas\Diactoros\UploadedFile;

class ImageValidationTraitTest extends TestCase
{
    use ImageValidationTrait;

    /**
     * Path to the file fixtures.
     *
     * @var string
     */
    protected string $fileFixtures;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->fileFixtures = Plugin::path('FileStorage') . 'tests' . DS . 'Fixture' . DS . 'File' . DS;
    }

    /**
     * @return void
     */
    public function testIsValidImageWithValidJpeg(): void
    {
        $file = new UploadedFile(
            $this->fileFixtures . 'titus.jpg',
            filesize($this->fileFixtures . 'titus.jpg'),
            UPLOAD_ERR_OK,
            'titus.jpg',
            'image/jpeg',
        );

        $this->assertTrue(static::isValidImage($file));
    }

    /**
     * @return void
     */
    public function testIsValidImageWithValidPng(): void
    {
        $file = new UploadedFile(
            $this->fileFixtures . 'cake.icon.png',
            filesize($this->fileFixtures . 'cake.icon.png'),
            UPLOAD_ERR_OK,
            'cake.icon.png',
            'image/png',
        );

        $this->assertTrue(static::isValidImage($file));
    }

    /**
     * @return void
     */
    public function testIsValidImageWithTextFile(): void
    {
        // Create a temporary text file
        $tmpFile = TMP . 'test-text-file.txt';
        file_put_contents($tmpFile, 'This is not an image');

        $file = new UploadedFile(
            $tmpFile,
            filesize($tmpFile),
            UPLOAD_ERR_OK,
            'test.txt',
            'text/plain',
        );

        $this->assertFalse(static::isValidImage($file));

        unlink($tmpFile);
    }

    /**
     * @return void
     */
    public function testIsValidImageWithCustomAllowedTypes(): void
    {
        $file = new UploadedFile(
            $this->fileFixtures . 'cake.icon.png',
            filesize($this->fileFixtures . 'cake.icon.png'),
            UPLOAD_ERR_OK,
            'cake.icon.png',
            'image/png',
        );

        // PNG should pass when PNG is allowed
        $this->assertTrue(static::isValidImage($file, [IMAGETYPE_PNG]));

        // PNG should fail when only JPEG is allowed
        $this->assertFalse(static::isValidImage($file, [IMAGETYPE_JPEG]));
    }

    /**
     * @return void
     */
    public function testIsValidImageWithArrayFormat(): void
    {
        $check = [
            'tmp_name' => $this->fileFixtures . 'titus.jpg',
            'name' => 'titus.jpg',
            'type' => 'image/jpeg',
            'size' => filesize($this->fileFixtures . 'titus.jpg'),
            'error' => UPLOAD_ERR_OK,
        ];

        $this->assertTrue(static::isValidImage($check));
    }

    /**
     * @return void
     */
    public function testIsValidImageWithMissingTmpName(): void
    {
        $check = [
            'name' => 'test.jpg',
        ];

        $this->assertFalse(static::isValidImage($check));
    }

    /**
     * @return void
     */
    public function testIsAboveMinWidthWithNonImageFile(): void
    {
        // Create a temporary text file
        $tmpFile = TMP . 'test-not-image.txt';
        file_put_contents($tmpFile, 'This is not an image');

        $file = new UploadedFile(
            $tmpFile,
            filesize($tmpFile),
            UPLOAD_ERR_OK,
            'test.txt',
            'text/plain',
        );

        // Should return false instead of throwing an error
        $this->assertFalse(static::isAboveMinWidth($file, 50));

        unlink($tmpFile);
    }

    /**
     * @return void
     */
    public function testIsBelowMaxWidthWithNonImageFile(): void
    {
        $tmpFile = TMP . 'test-not-image.txt';
        file_put_contents($tmpFile, 'This is not an image');

        $file = new UploadedFile(
            $tmpFile,
            filesize($tmpFile),
            UPLOAD_ERR_OK,
            'test.txt',
            'text/plain',
        );

        $this->assertFalse(static::isBelowMaxWidth($file, 400));

        unlink($tmpFile);
    }

    /**
     * @return void
     */
    public function testIsAboveMinHeightWithNonImageFile(): void
    {
        $tmpFile = TMP . 'test-not-image.txt';
        file_put_contents($tmpFile, 'This is not an image');

        $file = new UploadedFile(
            $tmpFile,
            filesize($tmpFile),
            UPLOAD_ERR_OK,
            'test.txt',
            'text/plain',
        );

        $this->assertFalse(static::isAboveMinHeight($file, 50));

        unlink($tmpFile);
    }

    /**
     * @return void
     */
    public function testIsBelowMaxHeightWithNonImageFile(): void
    {
        $tmpFile = TMP . 'test-not-image.txt';
        file_put_contents($tmpFile, 'This is not an image');

        $file = new UploadedFile(
            $tmpFile,
            filesize($tmpFile),
            UPLOAD_ERR_OK,
            'test.txt',
            'text/plain',
        );

        $this->assertFalse(static::isBelowMaxHeight($file, 400));

        unlink($tmpFile);
    }

    /**
     * @return void
     */
    public function testDimensionMethodsWithValidImage(): void
    {
        $file = new UploadedFile(
            $this->fileFixtures . 'titus.jpg',
            filesize($this->fileFixtures . 'titus.jpg'),
            UPLOAD_ERR_OK,
            'titus.jpg',
            'image/jpeg',
        );

        // titus.jpg is larger than 50px
        $this->assertTrue(static::isAboveMinWidth($file, 50));
        $this->assertTrue(static::isAboveMinHeight($file, 50));

        // titus.jpg is larger than 400px wide (based on existing test)
        $this->assertFalse(static::isBelowMaxWidth($file, 400));
    }
}
