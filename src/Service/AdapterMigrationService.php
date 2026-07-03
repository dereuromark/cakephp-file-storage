<?php declare(strict_types=1);

namespace FileStorage\Service;

use Cake\Core\Configure;
use Cake\ORM\Locator\LocatorAwareTrait;
use Exception;
use FileStorage\Model\Entity\FileStorage;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use RuntimeException;

class AdapterMigrationService
{
    use LocatorAwareTrait;

    /**
     * @param string $sourceAdapter
     * @param string $targetAdapter
     * @param array{model?: string|null, collection?: string|null, dryRun?: bool, deleteSource?: bool, overwrite?: bool, limit?: int|null} $options
     *
     * @throws \RuntimeException
     *
     * @return \FileStorage\Service\AdapterMigrationReport
     */
    public function run(string $sourceAdapter, string $targetAdapter, array $options = []): AdapterMigrationReport
    {
        if ($sourceAdapter === $targetAdapter) {
            throw new RuntimeException('Source and target adapters must be different.');
        }

        /** @var \PhpCollective\Infrastructure\Storage\FileStorage|null $fileStorage */
        $fileStorage = Configure::read('FileStorage.behaviorConfig.fileStorage');
        if ($fileStorage === null) {
            throw new RuntimeException('FileStorage adapter service is not configured.');
        }

        $source = $fileStorage->getStorage($sourceAdapter);
        $target = $fileStorage->getStorage($targetAdapter);

        $dryRun = (bool)($options['dryRun'] ?? false);
        $deleteSource = (bool)($options['deleteSource'] ?? false);
        $overwrite = (bool)($options['overwrite'] ?? false);
        $checkedRows = 0;
        $migratedRows = 0;
        $copiedFiles = 0;
        $deletedSourceFiles = 0;
        $skippedRows = [];
        $missingFiles = [];
        $failures = [];

        foreach ($this->streamRows($sourceAdapter, $options) as $entity) {
            $checkedRows++;

            $paths = $this->pathsFor($entity);
            if (!$paths) {
                $skippedRows[] = sprintf('ID %s has no path data.', $entity->id);

                continue;
            }

            $missing = $this->missingPaths($source, $paths);
            if ($missing) {
                $missingFiles[] = sprintf('ID %s missing: %s', $entity->id, implode(', ', $missing));

                continue;
            }

            if (!$overwrite) {
                $existing = $this->existingPaths($target, $paths);
                if ($existing) {
                    $skippedRows[] = sprintf('ID %s target exists: %s', $entity->id, implode(', ', $existing));

                    continue;
                }
            }

            try {
                if (!$dryRun) {
                    foreach ($paths as $path) {
                        $this->copyPath($source, $target, $path);
                    }

                    $this->fetchTable('FileStorage.FileStorage')->updateAll(
                        ['adapter' => $targetAdapter],
                        ['id' => $entity->id],
                    );

                    if ($deleteSource) {
                        foreach ($paths as $path) {
                            $source->delete($path);
                            $deletedSourceFiles++;
                        }
                    }
                }

                $copiedFiles += count($paths);
                $migratedRows++;
            } catch (Exception $e) {
                $failures[] = sprintf('ID %s failed: %s', $entity->id, $e->getMessage());
            }
        }

        return new AdapterMigrationReport(
            dryRun: $dryRun,
            checkedRows: $checkedRows,
            migratedRows: $migratedRows,
            copiedFiles: $copiedFiles,
            deletedSourceFiles: $deletedSourceFiles,
            skippedRows: $skippedRows,
            missingFiles: $missingFiles,
            failures: $failures,
        );
    }

    /**
     * @param string $sourceAdapter
     * @param array{model?: string|null, collection?: string|null, limit?: int|null} $options
     *
     * @return iterable<\FileStorage\Model\Entity\FileStorage>
     */
    protected function streamRows(string $sourceAdapter, array $options): iterable
    {
        $conditions = ['adapter' => $sourceAdapter];
        if (($options['model'] ?? null) !== null && $options['model'] !== '') {
            $conditions['model'] = $options['model'];
        }
        if (($options['collection'] ?? null) !== null && $options['collection'] !== '') {
            $conditions['collection'] = $options['collection'];
        }

        $query = $this->fetchTable('FileStorage.FileStorage')
            ->find()
            ->where($conditions);
        if (($options['limit'] ?? null) !== null) {
            $query->limit((int)$options['limit']);
        }

        foreach ($query as $entity) {
            if (!$entity instanceof FileStorage) {
                continue;
            }

            yield $entity;
        }
    }

    /**
     * @param \FileStorage\Model\Entity\FileStorage $entity
     *
     * @return array<int, string>
     */
    protected function pathsFor(FileStorage $entity): array
    {
        $paths = [];
        if ($entity->path) {
            $paths[] = $entity->path;
        }

        foreach ((array)$entity->variants as $variant) {
            if (!empty($variant['path']) && is_string($variant['path'])) {
                $paths[] = $variant['path'];
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param \League\Flysystem\FilesystemAdapter $adapter
     * @param array<int, string> $paths
     *
     * @return array<int, string>
     */
    protected function missingPaths(FilesystemAdapter $adapter, array $paths): array
    {
        $missing = [];
        foreach ($paths as $path) {
            if (!$adapter->fileExists($path)) {
                $missing[] = $path;
            }
        }

        return $missing;
    }

    /**
     * @param \League\Flysystem\FilesystemAdapter $adapter
     * @param array<int, string> $paths
     *
     * @return array<int, string>
     */
    protected function existingPaths(FilesystemAdapter $adapter, array $paths): array
    {
        $existing = [];
        foreach ($paths as $path) {
            if ($adapter->fileExists($path)) {
                $existing[] = $path;
            }
        }

        return $existing;
    }

    /**
     * @param \League\Flysystem\FilesystemAdapter $source
     * @param \League\Flysystem\FilesystemAdapter $target
     * @param string $path
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function copyPath(FilesystemAdapter $source, FilesystemAdapter $target, string $path): void
    {
        $stream = $source->readStream($path);
        if (!is_resource($stream)) {
            throw new RuntimeException(sprintf('Could not open source stream for `%s`.', $path));
        }

        try {
            $target->writeStream($path, $stream, new Config());
        } finally {
            fclose($stream);
        }
    }
}
