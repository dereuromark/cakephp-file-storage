<?php

declare(strict_types = 1);

namespace FileStorage\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Core\InstanceConfigTrait;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Exception;
use FileStorage\FileStorage\DataTransformer;
use FileStorage\FileStorage\DataTransformerInterface;
use FileStorage\Model\Entity\FileStorage;
use PhpCollective\Infrastructure\Storage\FileInterface;
use PhpCollective\Infrastructure\Storage\Processor\ProcessorInterface;
use RuntimeException;

/**
 * TODO: Fix force option to only overwrite if set.
 */
class ImageVariantGenerateCommand extends Command
{
    use EventDispatcherTrait;
    use InstanceConfigTrait;

    /**
     * Storage Table Object
     *
     * @var \Cake\ORM\Table
     */
    protected $Table;

    /**
     * @var int
     */
    protected $limit = 10;

    /**
     * Default config
     *
     * @var array<string, mixed>
     */
    protected $_defaultConfig = [
        'defaultStorageConfig' => 'Local',
        'ignoreEmptyFile' => true,
        'fileField' => 'file',
        'fileStorage' => null,
        'fileProcessor' => null,
        'fileValidator' => null,
    ];

    /**
     * @var \FileStorage\FileStorage\DataTransformerInterface|null
     */
    protected $transformer;

    /**
     * @var \PhpCollective\Infrastructure\Storage\Processor\ProcessorInterface|null
     */
    protected $processor;

    /**
     * Implement this method with your command's logic.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     *
     * @return int|null|void The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->setConfig(Configure::read('FileStorage.behaviorConfig'));

        $storageTable = (string)$args->getOption('storage');
        try {
            $this->Table = TableRegistry::getTableLocator()->get($storageTable);
        } catch (Exception $e) {
            $io->abort($e->getMessage());
        }

        if ($args->getOption('limit')) {
            $this->limit = (int)$args->getOption('limit');
        }

        $model = $args->getArgumentAt(0);
        $collection = $args->getArgumentAt(1);
        $variant = $args->getArgumentAt(2);
        $key = $model;
        // Hack for now
        if ($key) {
            $key = $collection;
        }
        if ($collection) {
            $key .= '.' . $collection;
        }
        if ($variant) {
            $key .= '.' . $variant;
        }

        if ($key) {
            $operations = Configure::read('FileStorage.imageVariants.' . $key);
        } else {
            $operations = Configure::read('FileStorage.imageVariants');
        }

        if (!$operations) {
            $io->abort(__('Cannot find variants config for this model/collection.'));
        }

        if ($key) {
            if ($variant) {
                $operations = [$variant => $operations];
            }

            $operations = [
                $model => [
                    $collection => $operations,
                ],
            ];
        }

        return $this->_process($args, $io, $operations);
    }

    /**
     * @param \Cake\Console\Arguments $args
     * @param \Cake\Console\ConsoleIo $io
     * @param array<string, mixed> $operations
     *
     * @return int
     */
    protected function _process(Arguments $args, ConsoleIo $io, array $operations): int
    {
        foreach ($operations as $model => $collections) {
            foreach ($collections as $collection => $variants) {
                $io->out('### ' . $model . ' . ' . $collection);
                $totalImageCount = $this->_getCount($model, $collection);

                if ($totalImageCount === 0) {
                    $io->out(__('No images found for this model/collection'));

                    continue;
                }

                $io->out(__('{0} image file(s) will be processed', $totalImageCount));

                $options = $args->getOptions();
                $this->_loop($io, $model, $collection, $variants, $options);
            }
        }

        return static::CODE_SUCCESS;
    }

    /**
     * Loops through image records and performs requested operation on them.
     *
     * @param \Cake\Console\ConsoleIo $io
     * @param string $model
     * @param string $collection
     * @param array<string, mixed> $operations
     * @param array<string, mixed> $options
     *
     * @return void
     */
    protected function _loop(ConsoleIo $io, string $model, string $collection, array $operations = [], array $options = []): void
    {
        $offset = 0;
        $limit = $this->limit;

        /** @var \PhpCollective\Infrastructure\Storage\FileStorage|null $storage */
        $storage = Configure::read('FileStorage.behaviorConfig.fileStorage');
        if (!$storage) {
            $io->abort(sprintf('Invalid adapter config `%s` provided!', $options['adapter']));
        }
        $adapter = $storage->getStorage($options['adapter']);

        do {
            $images = $this->_getRecords($model, $collection, $limit, $offset);
            if ($images) {
                foreach ($images as $image) {
                    $payload = [
                        'entity' => $image,
                        'storage' => $adapter,
                        'operations' => $operations,
                        'table' => $this->Table,
                        'options' => $options,
                    ];
                    //$Event = new Event('ImageVariant.generate', $this->Table, $payload);
                    //EventManager::instance()->dispatch($Event);

                    if (empty($options['dryRun'])) {
                        $this->_processEntity($image, $operations);
                    }

                    $io->verbose(__('- ID {0} processed', $image->id));
                }
            }
            $offset += $limit;
        } while ($images);
    }

    /**
     * @throws \RuntimeException
     *
     * @return \PhpCollective\Infrastructure\Storage\Processor\ProcessorInterface
     */
    protected function getFileProcessor(): ProcessorInterface
    {
        if ($this->processor !== null) {
            return $this->processor;
        }

        if ($this->getConfig('fileProcessor') instanceof ProcessorInterface) {
            $this->processor = $this->getConfig('fileProcessor');
        }

        if ($this->processor === null) {
            throw new RuntimeException('No processor found');
        }

        return $this->processor;
    }

    /**
     * Processes images
     *
     * @param \PhpCollective\Infrastructure\Storage\FileInterface $file File
     * @param \FileStorage\Model\Entity\FileStorage $entity
     * @param array $operations
     *
     * @return \PhpCollective\Infrastructure\Storage\FileInterface
     */
    public function processImages(FileInterface $file, EntityInterface $entity, array $operations): FileInterface
    {
        if (!$operations) {
            return $file;
        }

        $file = $file->withVariants($operations, false);

        return $file;
    }

    /**
     * @param \Cake\Datasource\EntityInterface $entity Entity
     *
     * @return \PhpCollective\Infrastructure\Storage\FileInterface
     */
    public function entityToFileObject(EntityInterface $entity): FileInterface
    {
        return $this->getTransformer()->entityToFileObject($entity);
    }

    /**
     * @param \PhpCollective\Infrastructure\Storage\FileInterface $file File
     * @param \Cake\Datasource\EntityInterface|null $entity
     *
     * @return \Cake\Datasource\EntityInterface
     */
    public function fileObjectToEntity(FileInterface $file, ?EntityInterface $entity)
    {
        return $this->getTransformer()->fileObjectToEntity($file, $entity);
    }

    /**
     * @return \FileStorage\FileStorage\DataTransformerInterface
     */
    protected function getTransformer(): DataTransformerInterface
    {
        if ($this->transformer !== null) {
            return $this->transformer;
        }

        if (!$this->getConfig('dataTransformer') instanceof DataTransformerInterface) {
            $this->transformer = new DataTransformer(
                $this->table(),
            );
        }

        /** @var \FileStorage\FileStorage\DataTransformerInterface */
        return $this->transformer;
    }

    /**
     * Get the table instance this behavior is bound to.
     *
     * @return \Cake\ORM\Table The bound table instance.
     */
    public function table(): Table
    {
        return $this->Table;
    }

    /**
     * @inheritDoc
     */
    public function getOptionParser(): ConsoleOptionParser
    {
        $parser = parent::getOptionParser();
        $parser->setDescription([
            __('Command for (re)generating image variants.'),
        ]);
        $parser->addOption('storage', [
            'short' => 's',
            'help' => __('The storage table for image processing you want to use.'),
            'default' => 'FileStorage.FileStorage',
        ]);
        $parser->addOption('limit', [
            'short' => 'l',
            'help' => __('Limits the amount of records to be processed in one batch'),
        ]);
        $parser->addOption('dryRun', [
            'short' => 'd',
            'help' => __('Dry-Run only.'),
            'boolean' => true,
        ]);
        $parser->addOptions(
            [
                'adapter' => [
                    'short' => 'a',
                    'help' => __('The adapter config name to use.'),
                    'default' => 'Local',
                ],
                /*
                'force' => [
                    'short' => 'f',
                    'help' => __('Force overwriting of existing files (e.g. after a config change).'),
                    'boolean' => true,
                ],
                */
            ],
        );
        $parser->addArguments(
            [
                'model' => [
                    'help' => __('Model name of the images to generate'),
                    'required' => false,
                ],
                'collection' => [
                    'help' => __('Collection name of the images to generate.'),
                    'required' => false,
                ],
                'variant' => [
                    'help' => __('The image variant (omit for all). Careful: This currently wipes the other variants.'),
                    'required' => false,
                ],
            ],
        );

        return $parser;
    }

    /**
     * Gets the amount of images for a model in the DB.
     *
     * @param string $model
     * @param string $collection
     * @param array $extensions
     *
     * @return int
     */
    protected function _getCount(string $model, string $collection, array $extensions = ['jpg', 'png', 'jpeg']): int
    {
        $conditions = [
            'model' => $model,
            'collection' => $collection,
            'extension IN' => $extensions,
        ];

        return $this->Table
            ->find()
            ->where($conditions)
            ->count();
    }

    /**
     * Gets the chunk of records for the image processing
     *
     * @param string $model
     * @param string $collection
     * @param int $limit
     * @param int $offset
     * @param array $extensions
     *
     * @return array<\FileStorage\Model\Entity\FileStorage>
     */
    protected function _getRecords(string $model, string $collection, int $limit, int $offset, array $extensions = ['jpg', 'png', 'jpeg']): array
    {
        $conditions = [
            'model' => $model,
            'collection' => $collection,
            'extension IN' => $extensions,
        ];

        return $this->Table
            ->find()
            ->where($conditions)
            ->limit($limit)
            ->offset($offset)
            ->all()
            ->toArray();
    }

    /**
     * @param \FileStorage\Model\Entity\FileStorage $image
     * @param array $operations
     *
     * @return void
     */
    protected function _processEntity(FileStorage $image, array $operations): void
    {
        $file = $this->entityToFileObject($image);

        $file = $this->processImages($file, $image, $operations);

        $processor = $this->getFileProcessor();

        $this->dispatchEvent('FileStorage.beforeFileProcessing', [
            'entity' => $image,
            'file' => $file,
        ], $this->table());

        $file = $processor->process($file);

        $this->dispatchEvent('FileStorage.afterFileProcessing', [
            'entity' => $image,
            'file' => $file,
        ], $this->table());

        $image = $this->fileObjectToEntity($file, $image);

        $tableConfig = $this->table()->behaviors()->get('FileStorage')->getConfig();
        $this->table()->removeBehavior('FileStorage');
        $this->table()->saveOrFail($image);
        $this->table()->addBehavior('FileStorage.FileStorage', $tableConfig);
    }
}
