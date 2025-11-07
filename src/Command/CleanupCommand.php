<?php declare(strict_types=1);

namespace FileStorage\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

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

        $conditions = [];
        if ($model) {
            $conditions['model'] = $model;
        }
        if ($collection) {
            $conditions['collection'] = $collection;
        }

        $query = $this->getTableLocator()->get('FileStorage.FileStorage')->find()
            ->where($conditions);

        /** @var array<\FileStorage\Model\Entity\FileStorage> $images */
        $images = $query
            ->all()
            ->toArray();
        $io->out('Checking ' . count($images) . ' images...');

        $this->removeOrphanedImages($args, $io);
        $this->removeOrphanedFiles($images, $args, $io);

        $this->checkImageFileExistence($images, $args, $io);
    }

    /**
     * @param array<\FileStorage\Model\Entity\FileStorage> $images
     * @param \Cake\Console\Arguments $args
     * @param \Cake\Console\ConsoleIo $io
     *
     * @return void
     */
    protected function removeOrphanedFiles(array $images, Arguments $args, ConsoleIo $io): void
    {
        $files = [];
        foreach ($images as $image) {
            $files[] = WWW_ROOT . Configure::read('FileStorage.pathPrefix') . $image->path;
            foreach ($image->variants as $variant => $details) {
                $files[] = WWW_ROOT . Configure::read('FileStorage.pathPrefix') . $details['path'];
            }
        }

        /** @var \PhpCollective\Infrastructure\Storage\FileStorage|null $fileStorage */
        $fileStorage = Configure::read('FileStorage.behaviorConfig.fileStorage');
        if (!$fileStorage) {
            $io->warning('FileStorage not configured, skipping orphaned file removal.');

            return;
        }

        // Note: This orphaned file removal still requires local filesystem access
        // as it needs to iterate through directories. For non-local adapters,
        // this feature may not be applicable.
        $path = WWW_ROOT . Configure::read('FileStorage.pathPrefix');

        $model = $args->getArgument('model');
        $collection = $args->getArgument('collection');
        if ($model) {
            $path .= $model . DS;
        }
        if ($collection) {
            $path .= $collection . DS;
        }

        if (!is_dir($path)) {
            $io->info('Path does not exist or is not accessible: ' . $path);

            return;
        }

        $directory = new RecursiveDirectoryIterator(
            $path,
            FilesystemIterator::SKIP_DOTS,
        );
        $contents = new RecursiveIteratorIterator(
            $directory,
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($contents as $file) {
            $filePath = (string)$file;
            if (!is_file($filePath)) {
                continue;
            }

            if (in_array($filePath, $files)) {
                continue;
            }

            $io->warning('Deleting orphaned file: ' . $filePath);
            if ($args->getOption('dryRun')) {
                continue;
            }

            unlink($filePath);
        }
    }

    /**
     * @param \Cake\Console\Arguments $args
     * @param \Cake\Console\ConsoleIo $io
     *
     * @return void
     */
    protected function removeOrphanedImages(Arguments $args, ConsoleIo $io): void
    {
        $model = $args->getArgument('model');
        $collection = $args->getArgument('collection');
        $conditions = [
            'foreign_key IS' => null,
        ];
        if ($model) {
            $conditions['model'] = $model;
        }
        if ($collection) {
            $conditions['collection'] = $collection;
        }

        $query = $this->getTableLocator()->get('FileStorage.FileStorage')->find()
            ->where($conditions);

        /** @var array<\FileStorage\Model\Entity\FileStorage> $images */
        $images = $query
            ->all()
            ->toArray();

        $io->info(count($images) . ' orphaned images found.');
        if ($args->getOption('dryRun')) {
            return;
        }

        foreach ($images as $image) {
            $io->verbose('- deleting orphaned image ' . $image->id);
            $this->getTableLocator()->get('FileStorage.FileStorage')->deleteOrFail($image);
        }
    }

    /**
     * @param array<\FileStorage\Model\Entity\FileStorage> $images
     * @param \Cake\Console\Arguments $args
     * @param \Cake\Console\ConsoleIo $io
     *
     * @return void
     */
    protected function checkImageFileExistence(array $images, Arguments $args, ConsoleIo $io): void
    {
        /** @var \PhpCollective\Infrastructure\Storage\FileStorage|null $fileStorage */
        $fileStorage = Configure::read('FileStorage.behaviorConfig.fileStorage');
        if (!$fileStorage) {
            $io->warning('FileStorage not configured, skipping existence check.');

            return;
        }

        foreach ($images as $image) {
            $io->verbose('Checking image ' . $image->id);

            // Use storage adapter to check file existence
            try {
                $adapter = $fileStorage->getStorage($image->adapter);
            } catch (\Exception $e) {
                $io->error('Could not get adapter for image ' . $image->id . ': ' . $e->getMessage());

                continue;
            }

            $missing = [];

            // Check main file
            if (!$adapter->has($image->path)) {
                $missing[] = 'main';
            }

            // Check variants
            foreach ($image->variants as $variant => $details) {
                $variantPath = $details['path'] ?? null;
                if ($variantPath && !$adapter->has($variantPath)) {
                    $missing[] = $variant;
                }
            }

            if (!$missing) {
                continue;
            }

            $io->error('Missing files for ' . $image->id . ': ' . implode(', ', $missing));
            if ($args->getOption('dryRun')) {
                continue;
            }

            $this->getTableLocator()->get('FileStorage.FileStorage')->delete($image);
            $io->verbose('- deleting image ' . $image->id);
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
            'Cleanup',
        );
        $parser->addArgument('model');
        $parser->addArgument('collection');
        $parser->addOption('dryRun', [
            'short' => 'd',
            'help' => __('Dry-Run only.'),
            'boolean' => true,
        ]);

        return $parser;
    }
}
