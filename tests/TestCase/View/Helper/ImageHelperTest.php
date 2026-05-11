<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\View\Helper;

use Cake\Core\Configure;
use Cake\Http\ServerRequest as Request;
use Cake\View\View;
use FileStorage\Test\TestCase\FileStorageTestCase;
use FileStorage\View\Helper\ImageHelper;
use PhpCollective\Infrastructure\Storage\Processor\Exception\VariantDoesNotExistException;

/**
 * ImageHelperTest
 *
 * @author Florian Krämer
 * @copy 2012 - 2017 Florian Krämer
 * @license MIT
 */
class ImageHelperTest extends FileStorageTestCase
{
    /**
     * Image Helper
     *
     * @var \FileStorage\View\Helper\ImageHelper
     */
    protected $helper;

    /**
     * Image Helper
     *
     * @var \Cake\View\View
     */
    protected $view;

    /**
     * Start Test
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->view = new View();
        $this->helper = new ImageHelper($this->view);

        $request = (new Request(['url' => 'contacts/add']))
            ->withAttribute('webroot', '/')
            ->withAttribute('base', '/');

        $this->helper->Html->getView()->setRequest($request);
    }

    /**
     * End Test
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
        unset($this->helper);
    }

    /**
     * @return void
     */
    public function testImageUrl()
    {
        $image = $this->FileStorage->newEntity([
            'filename' => 'testimage.jpg',
            'model' => 'Test',
            'foreign_key' => 1,
            'path' => 'test/path/testimage.jpg',
            'extension' => 'jpg',
            'adapter' => 'Local',
            'variants' => [
                't150' => [
                    'path' => 'test/path/testimage.c3f33c2a.jpg',
                    'url' => '',
                ],
            ],
        ]);

        $result = $this->helper->imageUrl($image, 't150', ['pathPrefix' => 'src/']);
        $this->assertSame('/src/test/path/testimage.c3f33c2a.jpg', $result);

        $result = $this->helper->imageUrl($image, null, ['pathPrefix' => 'src/']);
        $this->assertSame('/src/test/path/testimage.jpg', $result);
    }

    /**
     * @return void
     */
    public function testImageUrlInvalidArgumentException()
    {
        $this->expectException(VariantDoesNotExistException::class);
        $image = $this->FileStorage->newEntity([
            'id' => 'e479b480-f60b-11e1-a21f-0800200c9a66',
            'filename' => 'testimage.jpg',
            'model' => 'Test',
            'path' => 'test/path/',
            'extension' => 'jpg',
            'adapter' => 'Local',
        ], ['accessibleFields' => ['*' => true]]);

        $this->helper->imageUrl($image, 'invalid-version!');
    }

    /**
     * testFallbackImage
     *
     * @return void
     */
    public function testFallbackImage()
    {
        Configure::write('Media.fallbackImages.Test.t150', 't150fallback.png');

        $result = $this->helper->fallbackImage(['fallback' => true], 't150');
        $this->assertStringStartsWith('<img src="/img/placeholder/t150.jpg" alt=""', $result);

        $result = $this->helper->fallbackImage(['fallback' => 'something.png'], 't150');
        $this->assertStringStartsWith('<img src="/img/something.png" alt=""', $result);

        $result = $this->helper->fallbackImage([], 't150');
        $this->assertSame('', $result);
    }

    /**
     * @return void
     */
    public function testDisplay(): void
    {
        $image = $this->FileStorage->newEntity([
            'filename' => 'testimage.jpg',
            'model' => 'Test',
            'foreign_key' => 1,
            'path' => 'test/path/testimage.jpg',
            'extension' => 'jpg',
            'adapter' => 'Local',
            'variants' => [
                't150' => [
                    'path' => 'test/path/testimage.c3f33c2a.jpg',
                    'url' => '',
                ],
            ],
        ]);

        $result = $this->helper->display($image, 't150', ['pathPrefix' => 'src/']);
        $this->assertStringContainsString('<img src="/src/test/path/testimage.c3f33c2a.jpg"', $result);

        $result = $this->helper->display($image, null, ['pathPrefix' => 'src/']);
        $this->assertStringContainsString('<img src="/src/test/path/testimage.jpg"', $result);
    }

    /**
     * @return void
     */
    public function testDisplayWithNullImage(): void
    {
        $result = $this->helper->display(null, null, ['fallback' => 'placeholder.jpg']);
        $this->assertStringContainsString('<img src="/img/placeholder.jpg"', $result);
    }

    /**
     * @return void
     */
    public function testDisplayWithInvalidVariant(): void
    {
        $image = $this->FileStorage->newEntity([
            'id' => 'e479b480-f60b-11e1-a21f-0800200c9a66',
            'filename' => 'testimage.jpg',
            'model' => 'Test',
            'path' => 'test/path/testimage.jpg',
            'extension' => 'jpg',
            'adapter' => 'Local',
        ], ['accessibleFields' => ['*' => true]]);

        // When variant doesn't exist, it should fall back to fallback image
        $result = $this->helper->display($image, 'nonexistent', ['fallback' => 'error.jpg']);
        $this->assertStringContainsString('<img src="/img/error.jpg"', $result);
    }

    /**
     * @return void
     */
    public function testDisplayWithUrlInVariant(): void
    {
        $image = $this->FileStorage->newEntity([
            'filename' => 'testimage.jpg',
            'model' => 'Test',
            'foreign_key' => 1,
            'path' => 'test/path/testimage.jpg',
            'extension' => 'jpg',
            'adapter' => 'Local',
            'variants' => [
                't150' => [
                    'path' => 'test/path/testimage.c3f33c2a.jpg',
                    'url' => 'https://cdn.example.com/images/testimage.jpg',
                ],
            ],
        ]);

        $result = $this->helper->display($image, 't150');
        $this->assertStringContainsString('https://cdn.example.com/images/testimage.jpg', $result);
    }

    /**
     * Happy path for `picture()`: entity has AVIF + WebP variants alongside
     * the JPEG, helper renders a `<picture>` with two `<source>` entries
     * (one per modern format) plus a fallback `<img>` for the JPEG.
     *
     * @return void
     */
    public function testPictureRendersAllConfiguredFormats(): void
    {
        $image = $this->FileStorage->newEntity([
            'filename' => 'testimage.jpg',
            'model' => 'Test',
            'foreign_key' => 1,
            'path' => 'test/path/testimage.jpg',
            'extension' => 'jpg',
            'adapter' => 'Local',
            'variants' => [
                'medium' => [
                    'path' => 'test/path/testimage.medium.jpg',
                    'url' => '',
                ],
                'medium.webp' => [
                    'path' => 'test/path/testimage.medium.webp',
                    'url' => '',
                ],
                'medium.avif' => [
                    'path' => 'test/path/testimage.medium.avif',
                    'url' => '',
                ],
            ],
        ]);

        $result = $this->helper->picture($image, 'medium', ['pathPrefix' => 'src/']);

        $this->assertStringContainsString('<picture>', $result);
        $this->assertStringContainsString(
            '<source srcset="/src/test/path/testimage.medium.avif" type="image/avif">',
            $result,
        );
        $this->assertStringContainsString(
            '<source srcset="/src/test/path/testimage.medium.webp" type="image/webp">',
            $result,
        );
        $this->assertStringContainsString('<img src="/src/test/path/testimage.medium.jpg"', $result);
        $this->assertStringContainsString('</picture>', $result);

        // AVIF before WebP — preference order matters.
        $avifPos = strpos($result, 'type="image/avif"');
        $webpPos = strpos($result, 'type="image/webp"');
        $this->assertNotFalse($avifPos);
        $this->assertNotFalse($webpPos);
        $this->assertLessThan($webpPos, $avifPos);
    }

    /**
     * Missing alt-format variants are silently skipped — partial coverage is
     * fine. Here only WebP exists; AVIF is dropped from `<source>` listing
     * and the fallback `<img>` renders the JPEG as usual.
     *
     * @return void
     */
    public function testPictureSkipsMissingFormats(): void
    {
        $image = $this->FileStorage->newEntity([
            'filename' => 'testimage.jpg',
            'model' => 'Test',
            'foreign_key' => 1,
            'path' => 'test/path/testimage.jpg',
            'extension' => 'jpg',
            'adapter' => 'Local',
            'variants' => [
                'medium' => ['path' => 'test/path/testimage.medium.jpg', 'url' => ''],
                'medium.webp' => ['path' => 'test/path/testimage.medium.webp', 'url' => ''],
            ],
        ]);

        $result = $this->helper->picture($image, 'medium', ['pathPrefix' => 'src/']);

        $this->assertStringContainsString('<source srcset="/src/test/path/testimage.medium.webp" type="image/webp">', $result);
        $this->assertStringNotContainsString('image/avif', $result);
    }

    /**
     * No alt-format variants at all → skip the `<picture>` wrapper entirely
     * and just emit the plain `<img>`. Avoids the visual cost of wrapping
     * the only child in a redundant element.
     *
     * @return void
     */
    public function testPictureFallsBackToPlainImgWhenNoFormatVariantsExist(): void
    {
        $image = $this->FileStorage->newEntity([
            'filename' => 'testimage.jpg',
            'model' => 'Test',
            'foreign_key' => 1,
            'path' => 'test/path/testimage.jpg',
            'extension' => 'jpg',
            'adapter' => 'Local',
            'variants' => [
                'medium' => ['path' => 'test/path/testimage.medium.jpg', 'url' => ''],
            ],
        ]);

        $result = $this->helper->picture($image, 'medium', ['pathPrefix' => 'src/']);

        $this->assertStringNotContainsString('<picture>', $result);
        $this->assertStringContainsString('<img src="/src/test/path/testimage.medium.jpg"', $result);
    }

    /**
     * Caller can override the format list — useful when an app only ships
     * WebP and doesn't have AVIF in its variant pipeline yet.
     *
     * @return void
     */
    public function testPictureHonorsCustomFormatList(): void
    {
        $image = $this->FileStorage->newEntity([
            'filename' => 'testimage.jpg',
            'model' => 'Test',
            'foreign_key' => 1,
            'path' => 'test/path/testimage.jpg',
            'extension' => 'jpg',
            'adapter' => 'Local',
            'variants' => [
                'medium' => ['path' => 'test/path/testimage.medium.jpg', 'url' => ''],
                'medium.webp' => ['path' => 'test/path/testimage.medium.webp', 'url' => ''],
                'medium.avif' => ['path' => 'test/path/testimage.medium.avif', 'url' => ''],
            ],
        ]);

        $result = $this->helper->picture(
            $image,
            'medium',
            ['pathPrefix' => 'src/', 'formats' => ['webp']],
        );

        $this->assertStringContainsString('image/webp', $result);
        // AVIF variant exists on the entity but was excluded from the
        // requested format list, so the source must not appear.
        $this->assertStringNotContainsString('image/avif', $result);
    }
}
