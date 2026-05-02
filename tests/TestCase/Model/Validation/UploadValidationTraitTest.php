<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\Model\Validation;

use Cake\TestSuite\TestCase;
use FileStorage\Model\Validation\UploadValidationTrait;
use Laminas\Diactoros\UploadedFile;
use Psr\Http\Message\UploadedFileInterface;

class UploadValidationTraitTest extends TestCase
{
    use UploadValidationTrait;

    /**
     * @return void
     */
    public function testHasAllowedExtensionAcceptsArrayUploadInList(): void
    {
        $check = ['name' => 'photo.JPG', 'tmp_name' => '/tmp/x', 'type' => 'image/jpeg'];

        $this->assertTrue(self::hasAllowedExtension($check, ['jpg', 'png']));
    }

    /**
     * @return void
     */
    public function testHasAllowedExtensionRejectsExtensionNotInList(): void
    {
        $check = ['name' => 'doc.pdf'];

        $this->assertFalse(self::hasAllowedExtension($check, ['jpg', 'png']));
    }

    /**
     * @return void
     */
    public function testHasAllowedExtensionRejectsEmptyAllowList(): void
    {
        $check = ['name' => 'photo.jpg'];

        $this->assertFalse(self::hasAllowedExtension($check, []));
    }

    /**
     * @return void
     */
    public function testHasAllowedExtensionRejectsMissingExtension(): void
    {
        $check = ['name' => 'README'];

        $this->assertFalse(self::hasAllowedExtension($check, ['jpg']));
    }

    /**
     * @return void
     */
    public function testHasAllowedExtensionAcceptsLeadingDotInAllowList(): void
    {
        $check = ['name' => 'photo.jpg'];

        $this->assertTrue(self::hasAllowedExtension($check, ['.jpg']));
    }

    /**
     * @return void
     */
    public function testHasAllowedExtensionWorksWithUploadedFileInterface(): void
    {
        $upload = $this->makeUploadedFile('photo.png');

        $this->assertTrue(self::hasAllowedExtension($upload, ['png']));
        $this->assertFalse(self::hasAllowedExtension($upload, ['gif']));
    }

    /**
     * @return void
     */
    public function testHasAllowedMimeTypeSniffsFromContents(): void
    {
        $tmp = $this->createTempFile("\xFF\xD8\xFF\xE0\x00\x10JFIF\x00", 'jpg');
        $check = ['name' => 'fake.txt', 'tmp_name' => $tmp, 'type' => 'text/plain'];

        $this->assertTrue(self::hasAllowedMimeType($check, ['image/jpeg']));
        $this->assertFalse(self::hasAllowedMimeType($check, ['image/png']));

        unlink($tmp);
    }

    /**
     * @return void
     */
    public function testHasAllowedMimeTypeFallsBackToClientHeaderWhenSniffDisabled(): void
    {
        $tmp = $this->createTempFile('plain text content', 'txt');
        $check = ['name' => 'shell.txt', 'tmp_name' => $tmp, 'type' => 'application/x-php'];

        $this->assertTrue(self::hasAllowedMimeType($check, ['application/x-php'], false));
        $this->assertFalse(self::hasAllowedMimeType($check, ['text/plain'], false));

        unlink($tmp);
    }

    /**
     * @return void
     */
    public function testHasAllowedMimeTypeRejectsEmptyAllowList(): void
    {
        $check = ['name' => 'a.jpg', 'tmp_name' => '', 'type' => 'image/jpeg'];

        $this->assertFalse(self::hasAllowedMimeType($check, []));
    }

    /**
     * @param string $contents
     * @param string $extension
     *
     * @return string
     */
    protected function createTempFile(string $contents, string $extension): string
    {
        $path = TMP . 'upload_validation_' . uniqid() . '.' . $extension;
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * @param string $clientFilename
     *
     * @return \Psr\Http\Message\UploadedFileInterface
     */
    protected function makeUploadedFile(string $clientFilename): UploadedFileInterface
    {
        $tmp = $this->createTempFile('placeholder', 'bin');

        return new UploadedFile($tmp, filesize($tmp) ?: 0, UPLOAD_ERR_OK, $clientFilename, 'application/octet-stream');
    }
}
