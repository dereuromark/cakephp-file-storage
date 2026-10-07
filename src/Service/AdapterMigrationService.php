<?php declare(strict_types=1);

namespace FileStorage\Service;

use Cake\Core\Configure;
use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Postgres;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use FileStorage\Model\Entity\FileStorage;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use RuntimeException;
use Throwable;

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

            if ($entity->blob_id !== null) {
                try {
                    $result = $this->migrateBlob($entity, $sourceAdapter, $targetAdapter, $source, $target, $dryRun, $deleteSource, $overwrite);
                    if ($result['skipped'] !== null) {
                        $skippedRows[] = $result['skipped'];
                    } elseif ($result['missing'] !== null) {
                        $missingFiles[] = $result['missing'];
                    } else {
                        $migratedRows++;
                        $copiedFiles += $result['copied'];
                        $deletedSourceFiles += $result['deleted'];
                    }
                } catch (Throwable $exception) {
                    $failures[] = sprintf('ID %s failed: %s', $entity->id, $exception->getMessage());
                }

                continue;
            }

            $table = $this->fetchTable('FileStorage.FileStorage');
            $migratedPaths = null;
            $operation = function () use ($table, $entity, $source, $target, $targetAdapter, $dryRun, $overwrite, &$skippedRows, &$missingFiles, &$migratedPaths): void {
                $query = $table->find()->where(['id' => $entity->id]);
                $driver = $table->getConnection()->getDriver();
                if (!$dryRun && ($driver instanceof Mysql || $driver instanceof Postgres)) {
                    $query->epilog('FOR UPDATE');
                }
                $fresh = $query->first();
                if (!$fresh instanceof FileStorage || $fresh->path !== $entity->path || $fresh->adapter !== $entity->adapter || $fresh->blob_id !== $entity->blob_id) {
                    $skippedRows[] = sprintf('ID %s path, adapter or blob reference changed.', $entity->id);

                    return;
                }
                $entity = $fresh;
                $paths = $this->pathsFor($entity);
                if (!$paths) {
                    $skippedRows[] = sprintf('ID %s has no path data.', $entity->id);

                    return;
                }

                $missing = $this->missingPaths($source, $paths);
                if ($missing) {
                    $missingFiles[] = sprintf('ID %s missing: %s', $entity->id, implode(', ', $missing));

                    return;
                }

                if (!$overwrite) {
                    $existing = $this->existingPaths($target, $paths);
                    if ($existing) {
                        $skippedRows[] = sprintf('ID %s target exists: %s', $entity->id, implode(', ', $existing));

                        return;
                    }
                }

                if (!$dryRun) {
                    foreach ($paths as $path) {
                        $this->copyPath($source, $target, $path);
                    }

                    $this->fetchTable('FileStorage.FileStorage')->updateAll(
                        ['adapter' => $targetAdapter],
                        ['id' => $entity->id],
                    );
                }

                $migratedPaths = $paths;
            };
            try {
                if ($dryRun) {
                    $operation();
                } else {
                    $table->getConnection()->transactional($operation);
                }
                if ($migratedPaths === null) {
                    continue;
                }
                if (!$dryRun && $deleteSource) {
                    foreach ($migratedPaths as $path) {
                        $source->delete($path);
                        $deletedSourceFiles++;
                    }
                }
                $copiedFiles += count($migratedPaths);
                $migratedRows++;
            } catch (Throwable $exception) {
                $failures[] = sprintf('ID %s failed: %s', $entity->id, $exception->getMessage());
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
     * @param \FileStorage\Model\Entity\FileStorage $entity
     * @param string $sourceName
     * @param string $targetName
     * @param \League\Flysystem\FilesystemAdapter $source
     * @param \League\Flysystem\FilesystemAdapter $target
     * @param bool $dryRun
     * @param bool $deleteSource
     * @param bool $overwrite
     *
     * @throws \RuntimeException
     *
     * @return array{skipped: string|null, missing: string|null, copied: int, deleted: int, sourceVariants: array<int, string>}
     */
    protected function migrateBlob(FileStorage $entity, string $sourceName, string $targetName, FilesystemAdapter $source, FilesystemAdapter $target, bool $dryRun, bool $deleteSource, bool $overwrite): array
    {
        $table = $this->fetchTable('FileStorage.FileStorage');
        $operation = function () use ($table, $entity, $sourceName, $targetName, $source, $target, $dryRun, $deleteSource, $overwrite): array {
            $result = ['skipped' => null, 'missing' => null, 'copied' => 0, 'deleted' => 0, 'sourceVariants' => []];
            $query = $table->find()->where(['id' => $entity->id]);
            $driver = $table->getConnection()->getDriver();
            if (!$dryRun && ($driver instanceof Mysql || $driver instanceof Postgres)) {
                $query->epilog('FOR UPDATE');
            }
            $fresh = $query->first();
            if (!$fresh instanceof FileStorage || $fresh->adapter !== $sourceName || $fresh->blob_id === null) {
                $result['skipped'] = sprintf('ID %s source adapter or blob reference changed.', $entity->id);

                return $result;
            }
            $variants = [];
            foreach ((array)$fresh->variants as $variant) {
                $path = $variant['path'] ?? null;
                if (is_string($path) && $path !== '' && $path !== $fresh->path) {
                    $variants[] = $path;
                }
            }
            $variants = array_values(array_unique($variants));
            $registry = new BlobRegistry($table);
            $claim = $dryRun ? null : $registry->claim($targetName, (string)$fresh->hash, DateTime::now());
            $targetPath = $claim?->path;
            if ($dryRun) {
                $blob = $this->fetchTable('FileStorage.FileStorageBlobs')->find()
                    ->where(['adapter' => $targetName, 'hash' => $fresh->hash])->first();
                $targetPath = $blob?->get('path');
            }
            // A registered target blob whose file is gone is restored from the source.
            $restore = $targetPath !== null && !$target->fileExists($targetPath);
            $needsMain = $targetPath === null || $restore;
            $paths = $needsMain ? array_merge([(string)$fresh->path], $variants) : $variants;
            $missing = $this->missingPaths($source, $paths);
            if ($missing !== []) {
                $result['missing'] = sprintf('ID %s missing: %s', $entity->id, implode(', ', $missing));

                return $result;
            }
            $existing = $overwrite ? [] : $this->existingPaths($target, $variants);
            if ($existing !== []) {
                $result['skipped'] = sprintf('ID %s target exists: %s', $entity->id, implode(', ', $existing));

                return $result;
            }
            if (!$dryRun) {
                // The rollback cannot undo writes to the adapter. Files this attempt
                // created are removed again, or a retry would report "target exists".
                $created = [];
                try {
                    foreach ($variants as $path) {
                        $existed = $target->fileExists($path);
                        $this->copyPath($source, $target, $path);
                        if (!$existed) {
                            $created[] = $path;
                        }
                    }
                    if ($needsMain) {
                        $this->copyPath($source, $target, (string)$fresh->path, $targetPath);
                        // A restored registered blob stays: it is the right content for its row.
                        if ($targetPath === null) {
                            $created[] = (string)$fresh->path;
                        }
                    }
                    if ($claim === null) {
                        throw new RuntimeException('Missing target blob claim.');
                    }
                    if ($targetPath === null) {
                        $targetPath = (string)$fresh->path;
                        $registry->recordPath($claim->id, $targetPath);
                    }
                    $table->updateAll(['adapter' => $targetName, 'path' => $targetPath, 'blob_id' => $claim->id], ['id' => $fresh->id]);
                } catch (Throwable $exception) {
                    foreach ($created as $path) {
                        try {
                            $target->delete($path);
                        } catch (Throwable) {
                            // Best effort; the original failure is what gets reported.
                        }
                    }

                    throw $exception;
                }
                if ($deleteSource) {
                    $result['sourceVariants'] = $variants;
                }
            }
            $result['copied'] = count($paths);

            return $result;
        };
        if ($dryRun) {
            return $operation();
        }
        $result = null;
        $table->getConnection()->transactional(function () use ($operation, &$result): bool {
            $result = $operation();

            return $result['skipped'] === null && $result['missing'] === null;
        });
        if ($result === null) {
            throw new RuntimeException('Missing blob migration result.');
        }
        // Only after the commit: a rollback could not bring deleted files back.
        foreach ($result['sourceVariants'] as $path) {
            try {
                $source->delete($path);
                $result['deleted']++;
            } catch (Throwable) {
                // The row is migrated; a leftover source variant is only wasted space.
            }
        }

        return $result;
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

        // Ordered, so a limited run picks the same rows on every database.
        $query = $this->fetchTable('FileStorage.FileStorage')
            ->find()
            ->where($conditions)
            ->orderBy(['id' => 'ASC']);
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
     * @param string|null $targetPath Defaults to the source path.
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function copyPath(
        FilesystemAdapter $source,
        FilesystemAdapter $target,
        string $path,
        ?string $targetPath = null,
    ): void {
        $stream = $source->readStream($path);
        if (!is_resource($stream)) {
            throw new RuntimeException(sprintf('Could not open source stream for `%s`.', $path));
        }

        try {
            $target->writeStream($targetPath ?? $path, $stream, new Config());
        } finally {
            fclose($stream);
        }
    }
}
