<?php declare(strict_types=1);

namespace FileStorage\Service;

use Cake\Core\Configure;
use Cake\ORM\Locator\LocatorAwareTrait;
use Exception;
use FileStorage\Model\Entity\FileStorage;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Storage-tree cleanup logic, shared between the `file_storage cleanup`
 * CLI command and the `Admin/FileStorage::cleanup()` action.
 *
 * Three responsibilities:
 *
 * 1. Delete `file_storage` rows whose `foreign_key` is null (orphan rows).
 * 2. Walk the configured local filesystem path and remove files that have no
 *    matching `file_storage` row (orphan files on disk).
 * 3. Report rows whose backing main/variant files are missing on the
 *    configured storage adapter (consistency check).
 *
 * Dry-run mode performs all the *queries* but skips the actual mutations.
 */
class CleanupService
{
    use LocatorAwareTrait;

    /**
     * @param string|null $model Optional model alias filter.
     * @param string|null $collection Optional collection filter.
     * @param bool $dryRun When true, no rows or files are actually removed.
     *
     * @return \FileStorage\Service\CleanupReport
     */
    public function run(?string $model, ?string $collection, bool $dryRun): CleanupReport
    {
        $warnings = [];
        $table = $this->fetchTable('FileStorage.FileStorage');

        $scopeConditions = [];
        if ($model !== null && $model !== '') {
            $scopeConditions['model'] = $model;
        }
        if ($collection !== null && $collection !== '') {
            $scopeConditions['collection'] = $collection;
        }

        // Stream the rows in two passes instead of materializing the whole
        // file_storage table into PHP memory. On deployments with hundreds of
        // thousands of attachments the previous `->all()->toArray()` would OOM.
        // Each pass is a fresh query so the underlying PDO cursor stays
        // lazy / iterable-once and we never hold all rows at the same time.
        $checkedCount = 0;
        $deletedRows = $this->removeOrphanRows($scopeConditions, $dryRun);
        $deletedFiles = $this->removeOrphanFiles(
            $this->streamScoped($scopeConditions, $checkedCount),
            $model,
            $collection,
            $dryRun,
            $warnings,
        );
        $missingFiles = $this->collectMissingFiles($this->streamScoped($scopeConditions), $warnings);

        return new CleanupReport(
            dryRun: $dryRun,
            checkedCount: $checkedCount,
            deletedFiles: $deletedFiles,
            deletedRows: $deletedRows,
            missingFiles: $missingFiles,
            warnings: $warnings,
        );
    }

    /**
     * Lazy iterator over file_storage rows matching $scopeConditions.
     *
     * @param array<string, mixed> $scopeConditions
     * @param int $checkedCount Pass a variable by reference only if you want
     *     row counting (it is incremented as rows flow through); otherwise
     *     omit the parameter.
     *
     * @return iterable<\FileStorage\Model\Entity\FileStorage>
     */
    protected function streamScoped(array $scopeConditions, int &$checkedCount = 0): iterable
    {
        $table = $this->fetchTable('FileStorage.FileStorage');
        $query = $table->find()->where($scopeConditions);
        // disableBufferedResults() drops the ResultSet's internal row buffer
        // so memory stays O(1) regardless of result-set size. Trade-off: we
        // can't re-iterate the same query — every consumer here iterates
        // exactly once.
        $query->disableBufferedResults();
        foreach ($query as $image) {
            $checkedCount++;
            assert($image instanceof FileStorage);

            yield $image;
        }
    }

    /**
     * @param array<string, mixed> $scopeConditions
     * @param bool $dryRun
     *
     * @return int Number of orphan rows deleted (or that would be deleted).
     */
    protected function removeOrphanRows(array $scopeConditions, bool $dryRun): int
    {
        $table = $this->fetchTable('FileStorage.FileStorage');
        $conditions = $scopeConditions + ['foreign_key IS' => null];

        /** @var array<\FileStorage\Model\Entity\FileStorage> $orphans */
        $orphans = $table->find()->where($conditions)->all()->toArray();
        if ($dryRun) {
            return count($orphans);
        }

        $deleted = 0;
        foreach ($orphans as $orphan) {
            if ($table->delete($orphan)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * @param iterable<\FileStorage\Model\Entity\FileStorage> $images
     * @param string|null $model
     * @param string|null $collection
     * @param bool $dryRun
     * @param array<int, string> $warnings Out param.
     *
     * @return array<int, string> Absolute paths of orphan files on disk that were (or would be) deleted.
     */
    protected function removeOrphanFiles(
        iterable $images,
        ?string $model,
        ?string $collection,
        bool $dryRun,
        array &$warnings,
    ): array {
        // Hash-set keyed by absolute path for O(1) lookup; the iterator below can scan
        // many thousand files, and a linear `in_array` walk would turn that into O(n*m).
        $expected = [];
        $pathPrefix = (string)Configure::read('FileStorage.pathPrefix');
        foreach ($images as $image) {
            $expected[WWW_ROOT . $pathPrefix . $image->path] = true;
            foreach ($image->variants as $details) {
                if (isset($details['path'])) {
                    $expected[WWW_ROOT . $pathPrefix . $details['path']] = true;
                }
            }
        }

        $fileStorage = Configure::read('FileStorage.behaviorConfig.fileStorage');
        if ($fileStorage === null) {
            $warnings[] = 'FileStorage adapter not configured, skipping orphaned file removal.';

            return [];
        }

        $path = WWW_ROOT . $pathPrefix;
        if ($model !== null && $model !== '') {
            $path .= $model . DS;
        }
        if ($collection !== null && $collection !== '') {
            $path .= $collection . DS;
        }

        if (!is_dir($path)) {
            $warnings[] = sprintf('Path does not exist or is not accessible: %s', $path);

            return [];
        }

        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        $deleted = [];
        foreach ($iter as $file) {
            $filePath = (string)$file;
            if (!is_file($filePath) || isset($expected[$filePath])) {
                continue;
            }

            $deleted[] = $filePath;
            if (!$dryRun) {
                @unlink($filePath);
            }
        }

        return $deleted;
    }

    /**
     * @param iterable<\FileStorage\Model\Entity\FileStorage> $images
     * @param array<int, string> $warnings Out param.
     *
     * @return array<int, array{id: string|int, missing: array<int, string>}>
     */
    protected function collectMissingFiles(iterable $images, array &$warnings): array
    {
        $fileStorage = Configure::read('FileStorage.behaviorConfig.fileStorage');
        if ($fileStorage === null) {
            $warnings[] = 'FileStorage adapter not configured, skipping existence check.';

            return [];
        }

        $missing = [];
        foreach ($images as $image) {
            if (!$image->adapter || !$image->path) {
                continue;
            }

            try {
                $adapter = $fileStorage->getStorage($image->adapter);
            } catch (Exception $e) {
                $warnings[] = sprintf('Could not get adapter for image %s: %s', $image->id, $e->getMessage());

                continue;
            }

            $missingForImage = [];
            if (!$adapter->fileExists($image->path)) {
                $missingForImage[] = 'main';
            }
            foreach ($image->variants as $variant => $details) {
                $variantPath = $details['path'] ?? null;
                if ($variantPath && !$adapter->fileExists($variantPath)) {
                    $missingForImage[] = (string)$variant;
                }
            }

            if ($missingForImage) {
                $missing[] = ['id' => $image->id, 'missing' => $missingForImage];
            }
        }

        return $missing;
    }
}
