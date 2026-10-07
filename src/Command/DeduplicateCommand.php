<?php declare(strict_types=1);

namespace FileStorage\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use FileStorage\Service\ExistingFileDeduplicator;
use InvalidArgumentException;
use RuntimeException;

/**
 * Converts existing files to shared blobs.
 *
 * @author Mark Scherer
 * @license MIT
 */
class DeduplicateCommand extends Command
{
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $limit = $args->getOption('limit');
        if ($limit !== null && (filter_var($limit, FILTER_VALIDATE_INT) === false || (int)$limit < 1)) {
            $io->err('Limit must be a positive integer.');

            return static::CODE_ERROR;
        }
        $manifest = $args->getOption('manifest');
        if ($manifest !== null && !is_string($manifest)) {
            $io->err('Manifest must be a local file path.');

            return static::CODE_ERROR;
        }
        try {
            $report = (new ExistingFileDeduplicator())->run($args->getArgument('model'), $args->getArgument('collection'), [
                'dryRun' => (bool)$args->getOption('dryRun'),
                'hashOnly' => (bool)$args->getOption('hashOnly'),
                'limit' => $limit === null ? null : (int)$limit,
                'manifest' => $manifest,
            ]);
        } catch (RuntimeException | InvalidArgumentException $exception) {
            $io->err($exception->getMessage());

            return static::CODE_ERROR;
        }
        if ($report->dryRun) {
            $io->info(sprintf('%d candidate row(s), %d bytes, %d without hashes.', $report->candidates, $report->candidateBytes, $report->withoutHash));
            $io->info(sprintf('%d estimated duplicate(s).', $report->estimatedDuplicates));
        } else {
            $io->info(sprintf('%d row(s) hashed, %d converted, %d reused existing blobs.', $report->hashed, $report->converted, $report->reusedExisting));
            $io->info(sprintf('%d old file(s) kept, %d bytes.', $report->oldFilesKept, $report->bytesKept));
        }
        foreach ($report->skipped as $reason => $count) {
            $io->out(sprintf('%d skipped: %s.', $count, $reason));
        }
        $io->out(sprintf('%d missing file(s), %d failure(s), %d warning(s).', $report->missingFiles, $report->failures, $report->warningCount));
        foreach (array_merge($report->missingSamples, $report->failureSamples) as $message) {
            $io->error($message);
        }
        foreach ($report->warnings as $message) {
            $io->warning($message);
        }

        return static::CODE_SUCCESS;
    }

    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription('Convert existing files to deduplicated blobs. Old files are kept. On the local adapter, file_storage cleanup removes files no row references. Signed URLs issued before conversion stop working for converted rows. Do not run cleanup during conversion; use low traffic for cleanup. SQLite requires exclusive database use.');
        $parser->addArgument('model');
        $parser->addArgument('collection');
        $parser->addOption('dryRun', ['short' => 'd', 'boolean' => true, 'help' => 'Preview without hashing or writing.']);
        $parser->addOption('hashOnly', ['boolean' => true, 'help' => 'Fill empty hashes without converting files.']);
        $parser->addOption('limit', ['help' => 'Maximum rows attempted, a positive integer.']);
        $parser->addOption('manifest', ['help' => 'Append and flush converted row paths to a local tab separated file.']);

        return $parser;
    }
}
