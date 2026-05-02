<?php declare(strict_types=1);

namespace FileStorage\Service;

/**
 * Result object returned from {@see CleanupService::run()}.
 *
 * Lives as a plain DTO so both the CLI command and the admin UI can format
 * the same data differently. In dry-run mode `$deletedFiles` and
 * `$deletedRows` describe what *would* be done, not what was actually done.
 */
class CleanupReport
{
    /**
     * @param bool $dryRun Whether the run was a preview.
     * @param int $checkedCount Number of file_storage rows the run considered.
     * @param array<int, string> $deletedFiles Absolute paths of orphaned files (would-be) removed.
     * @param int $deletedRows Number of orphan rows (foreign_key IS NULL) (would-be) deleted.
     * @param array<int, array{id: string|int, missing: array<int, string>}> $missingFiles
     *   Rows whose backing files (main and/or variants) are missing on the storage adapter.
     * @param array<int, string> $warnings Non-fatal messages (e.g. fileStorage adapter not configured).
     */
    public function __construct(
        public readonly bool $dryRun,
        public readonly int $checkedCount,
        public readonly array $deletedFiles,
        public readonly int $deletedRows,
        public readonly array $missingFiles,
        public readonly array $warnings,
    ) {
    }
}
