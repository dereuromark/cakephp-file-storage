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

        /** @var \PhpCollective\Infrastructure\Storage\FileStorage $fileStorage */
        $fileStorage = Configure::read('FileStorage.behaviorConfig.fileStorage');
        //TODO: Make more agnostic apart from local file system.

        $path = WWW_ROOT . Configure::read('FileStorage.pathPrefix');

        $model = $args->getArgument('model');
        $collection = $args->getArgument('collection');
        if ($model) {
            $path .= $model . DS;
        }
        if ($collection) {
            $path .= $collection . DS;
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
            $path = (string)$file;
            if (!is_file($path)) {
                continue;
            }

            if (in_array($path, $files)) {
                continue;
            }

            $io->warning('Deleting orphaned file: ' . $path);
            if ($args->getOption('dryRun')) {
                continue;
            }

            unlink($path);
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
        foreach ($images as $image) {
            $io->verbose('Checking image ' . $image->id);

            $path = WWW_ROOT . Configure::read('FileStorage.pathPrefix') . $image->path;
            $missing = [];
            if (!file_exists($path)) {
                $missing[] = 'main';
            }
            foreach ($image->variants as $variant => $details) {
                $variantPath = $details['path'];
                if (!file_exists(WWW_ROOT . Configure::read('FileStorage.pathPrefix') . $variantPath)) {
                    $missing[] = $variant;
                }
            }

            if (!$missing) {
                continue;
            }

            $io->error('Missing images for ' . $image->id . ': ' . implode(', ', $missing));
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
