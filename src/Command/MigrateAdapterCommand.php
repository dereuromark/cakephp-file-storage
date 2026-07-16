<?php declare(strict_types=1);

namespace FileStorage\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use FileStorage\Service\AdapterMigrationService;
use RuntimeException;

class MigrateAdapterCommand extends Command
{
    /**
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io.
     *
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $source = (string)$args->getArgument('source');
        $target = (string)$args->getArgument('target');
        $model = $args->getOption('model');
        $collection = $args->getOption('collection');

        try {
            $report = (new AdapterMigrationService())->run($source, $target, [
                'model' => is_string($model) ? $model : null,
                'collection' => is_string($collection) ? $collection : null,
                'dryRun' => (bool)$args->getOption('dryRun'),
                'deleteSource' => (bool)$args->getOption('deleteSource'),
                'overwrite' => (bool)$args->getOption('overwrite'),
                'limit' => $args->getOption('limit') !== null ? (int)$args->getOption('limit') : null,
            ]);
        } catch (RuntimeException $e) {
            $io->err($e->getMessage());

            return static::CODE_ERROR;
        }

        $io->out(sprintf('Checked %d row(s).', $report->checkedRows));
        $io->success(sprintf(
            '%d row(s) %s, %d file(s) %s.',
            $report->migratedRows,
            $report->dryRun ? 'would be migrated' : 'migrated',
            $report->copiedFiles,
            $report->dryRun ? 'would be copied' : 'copied',
        ));

        if ($report->deletedSourceFiles) {
            $io->warning(sprintf('%d source file(s) deleted.', $report->deletedSourceFiles));
        }

        foreach ($report->skippedRows as $skipped) {
            $io->warning($skipped);
        }
        foreach ($report->missingFiles as $missing) {
            $io->error($missing);
        }
        foreach ($report->failures as $failure) {
            $io->error($failure);
        }

        return $report->failures ? static::CODE_ERROR : static::CODE_SUCCESS;
    }

    /**
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to update.
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Copy stored files from one configured adapter to another and update file_storage rows.')
            ->addArgument('source', [
                'help' => 'Source adapter config name, e.g. Local.',
                'required' => true,
            ])
            ->addArgument('target', [
                'help' => 'Target adapter config name, e.g. S3.',
                'required' => true,
            ])
            ->addOption('model', [
                'help' => 'Only migrate rows for this model.',
            ])
            ->addOption('collection', [
                'help' => 'Only migrate rows for this collection.',
            ])
            ->addOption('limit', [
                'help' => 'Maximum number of rows to inspect.',
            ])
            ->addOption('dryRun', [
                'short' => 'd',
                'help' => 'Preview only; do not copy files or update rows.',
                'boolean' => true,
            ])
            ->addOption('deleteSource', [
                'help' => 'Delete source files after a successful copy and row update.',
                'boolean' => true,
            ])
            ->addOption('overwrite', [
                'help' => 'Overwrite existing files on the target adapter.',
                'boolean' => true,
            ]);

        return $parser;
    }
}
