<?php declare(strict_types=1);

namespace TestApp\Storage\Processor;

use PhpCollective\Infrastructure\Storage\FileInterface;
use PhpCollective\Infrastructure\Storage\Processor\ProcessorInterface;
use RuntimeException;

class ImageDimensionsProcessor implements ProcessorInterface
{
    /**
     * @var string
     */
    protected $root;

    /**
     * @param string|null $root
     */
    public function __construct(?string $root = null)
    {
        $this->root = $root ?: TMP;
    }

    /**
     * @inheritDoc
     *
     * @throws \RuntimeException
     */
    public function process(FileInterface $file): FileInterface
    {
        $path = $this->root . $file->path();
        if (!file_exists($path)) {
            throw new RuntimeException('Cannot find file: ' . $path);
        }

        // phpcs:ignore
        $dimensions = @getimagesize($path);
        if (!$dimensions) {
            return $file;
        }

        $file = $file->withMetadataByKey('width', $dimensions[0])
            ->withMetadataByKey('height', $dimensions[1]);

        return $file;
    }
}
