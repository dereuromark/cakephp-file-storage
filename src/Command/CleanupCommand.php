<?php declare(strict_types=1);

namespace FileStorage\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use FileStorage\Service\CleanupService;

class CleanupCommand extends Command
{
    /**
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     *
     * @return int|null|void The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $model = $args->getArgument('model');
        $collection = $args->getArgument('collection');
        $dryRun = (bool)$args->getOption('dryRun');

        $blobsOnly = (bool)$args->getOption('blobsOnly');
        $service = new CleanupService();
        $uploadsOnly = (bool)$args->getOption('uploadsOnly');
        $report = $uploadsOnly ? $service->runUploads($dryRun)
            : ($blobsOnly ? $service->runBlobs($dryRun) : $service->run($model, $collection, $dryRun));

        if (!$blobsOnly && !$uploadsOnly) {
            $io->out(sprintf('Checking %d file storage rows...', $report->checkedCount));
            $io->info(sprintf('%d orphan row(s) %s.', $report->deletedRows, $dryRun ? 'would be deleted' : 'deleted'));
        }

        $io->info(sprintf('%d blob(s) %s.', count($report->deletedBlobs), $dryRun ? 'would be deleted' : 'deleted'));
        $io->info(sprintf('%d stray blob file(s) %s.', count($report->deletedStrayBlobs), $dryRun ? 'would be deleted' : 'deleted'));
        $io->info(sprintf('%d blob(s) skipped.', $report->skippedBlobs));

        $io->info(sprintf('%d upload(s), %d part file(s) %s; %d skipped.', $report->deletedUploads, $report->deletedUploadParts, $dryRun ? 'would be deleted' : 'deleted', $report->skippedUploads));

        foreach ($report->deletedFiles as $path) {
            $io->warning(sprintf('%s orphan file: %s', $dryRun ? 'Would delete' : 'Deleted', $path));
        }

        foreach ($report->missingFiles as $entry) {
            $io->error(sprintf('Missing files for %s: %s', $entry['id'], implode(', ', $entry['missing'])));
        }

        foreach ($report->warnings as $warning) {
            $io->warning($warning);
        }
    }

    /**
     * Display help for this console.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to update
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription(
            'Cleanup. Full runs sweep blobs and uploads across all models and collections, even when scoped.',
        );
        $parser->addArgument('model');
        $parser->addArgument('collection');
        $parser->addOption('dryRun', [
            'short' => 'd',
            'help' => __d('file_storage', 'Dry-Run only.'),
            'boolean' => true,
        ]);
        $parser->addOption('blobsOnly', [
            'short' => 'b',
            'help' => __d(
                'file_storage',
                'Only remove unreferenced deduplicated blobs and stray blob files. Orphan rows and files are left alone, model and collection are ignored.',
            ),
            'boolean' => true,
        ]);

        $parser->addOption('uploadsOnly', [
            'help' => 'Only remove expired upload sessions and abandoned part files.',
            'boolean' => true,
        ]);

        return $parser;
    }
}
