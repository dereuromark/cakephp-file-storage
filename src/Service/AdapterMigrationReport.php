<?php declare(strict_types=1);

namespace FileStorage\Service;

class AdapterMigrationReport
{
    /**
     * @param bool $dryRun
     * @param int $checkedRows
     * @param int $migratedRows
     * @param int $copiedFiles
     * @param int $deletedSourceFiles
     * @param array<int, string> $skippedRows
     * @param array<int, string> $missingFiles
     * @param array<int, string> $failures
     */
    public function __construct(
        public readonly bool $dryRun,
        public readonly int $checkedRows,
        public readonly int $migratedRows,
        public readonly int $copiedFiles,
        public readonly int $deletedSourceFiles,
        public readonly array $skippedRows,
        public readonly array $missingFiles,
        public readonly array $failures,
    ) {
    }
}
