<?php declare(strict_types=1);

namespace FileStorage\Service;

/**
 * Bounded results of an existing-file conversion run.
 *
 * @author Mark Scherer
 * @license MIT
 */
final class ExistingFileReport
{
    /**
     * @param bool $dryRun
     * @param int $hashed
     * @param int $converted
     * @param int $reusedExisting
     * @param int $oldFilesKept
     * @param int $bytesKept
     * @param array<string, int> $skipped
     * @param int $missingFiles
     * @param array<int, string> $missingSamples
     * @param int $failures
     * @param array<int, string> $failureSamples
     * @param array<int, string> $warnings
     * @param int $estimatedDuplicates
     * @param int $withoutHash
     * @param int $candidateBytes
     * @param int $candidates
     * @param int $warningCount
     */
    public function __construct(
        public readonly bool $dryRun,
        public readonly int $hashed = 0,
        public readonly int $converted = 0,
        public readonly int $reusedExisting = 0,
        public readonly int $oldFilesKept = 0,
        public readonly int $bytesKept = 0,
        public readonly array $skipped = [],
        public readonly int $missingFiles = 0,
        public readonly array $missingSamples = [],
        public readonly int $failures = 0,
        public readonly array $failureSamples = [],
        public readonly array $warnings = [],
        public readonly int $warningCount = 0,
        public readonly int $candidates = 0,
        public readonly int $candidateBytes = 0,
        public readonly int $withoutHash = 0,
        public readonly int $estimatedDuplicates = 0,
    ) {
    }
}
