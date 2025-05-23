<?php declare(strict_types=1);

namespace FileStorage\Shell;

use Cake\Console\ConsoleOptionParser;
use Cake\Console\Shell;
use Cake\Core\Configure;
use Cake\Datasource\ResultSetInterface;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\ORM\TableRegistry;
use Exception;

/**
 * TODO: Remove in favor of commands
 *
 * @deprecated Rewrite to command
 * @author Florian Krämer
 * @copyright 2012 - 2020 Florian Krämer
 * @license MIT
 */
class ImageVersionShell extends Shell
{
    /**
     * Storage Table Object
     *
     * @var \Cake\ORM\Table
     */
    public $Table;

    /**
     * Limit
     *
     * @var int
     */
    public $limit = 10;

    /**
     * @inheritDoc
     */
    public function getOptionParser(): ConsoleOptionParser
    {
        $parser = parent::getOptionParser();
        $parser->setDescription([
            __('Shell command for generating and removing image versions.'),
        ]);
        $parser->addOption('storageTable', [
            'short' => 's',
            'help' => __('The storage table for image processing you want to use.'),
            'default' => 'FileStorage.FileStorage',
        ]);
        $parser->addOption('limit', [
            'short' => 'l',
            'help' => __('Limits the amount of records to be processed in one batch'),
        ]);
        $parser->addSubcommands([
            'generate' => [
                'help' => __('<model> <identifier> Generate a new image version'),
                'parser' => [
                    'arguments' => [
                        'model' => [
                            'help' => __('Value of the model property of the images to generate'),
                            'required' => true,
                        ],
                        'identifier' => [
                            'help' => __('The image identifier (`model` field in `file_storage` table).'),
                            'required' => true,
                        ],
                    ],
                    'options' => [
                        'adapter' => [
                            'short' => 'a',
                            'help' => __('The adapter config name to use.'),
                            'default' => 'Local',
                        ],
                        'storageTable' => [
                            'short' => 's',
                            'help' => __('The storage table for image processing you want to use.'),
                            'default' => 'FileStorage.FileStorage',
                        ],
                        'limit' => [
                            'short' => 'l',
                            'help' => __('Limits the amount of records to be processed in one batch'),
                        ],
                        'keep-old-versions' => [
                            'short' => 'k',
                            'help' => __('Use this switch if you do not want to overwrite existing versions.'),
                            'boolean' => true,
                        ],
                    ],
                ],
            ],
            'remove' => [
                'help' => __('<model> <version> Remove an new image version'),
                'parser' => [
                    'arguments' => [
                        'model' => [
                            'help' => __('Value of the model property of the images to remove'),
                            'required' => true,
                        ],
                        'version' => [
                            'help' => __('Image version to remove'),
                            'required' => true,
                        ],
                    ],
                    'options' => [
                        'adapter' => [
                            'short' => 'a',
                            'help' => __('The adapter config name to use.'),
                            'default' => 'Local',
                        ],
                        'storageTable' => [
                            'short' => 's',
                            'help' => __('The storage table for image processing you want to use.'),
                            'default' => 'FileStorage.FileStorage',
                        ],
                        'limit' => [
                            'short' => 'l',
                            'help' => __('Limits the amount of records to be processed in one batch'),
                        ],
                    ],
                ],
            ],
            'regenerate' => [
                'help' => __('<model> Generates all image versions.'),
                'parser' => [
                    'arguments' => [
                        'model' => [
                            'help' => __('Value of the model property of the images to generate'),
                            'required' => false,
                        ],
                    ],
                    'options' => [
                        'adapter' => [
                            'short' => 'a',
                            'help' => __('The adapter config name to use.'),
                            'default' => 'Local',
                        ],
                        'storageTable' => [
                            'short' => 's',
                            'help' => __('The storage table for image processing you want to use.'),
                            'default' => 'FileStorage.FileStorage',
                        ],
                        'limit' => [
                            'short' => 'l',
                            'help' => __('Limits the amount of records to be processed in one batch'),
                        ],
                        'keep-old-versions' => [
                            'short' => 'k',
                            'help' => __('Use this switch if you do not want to overwrite existing versions.'),
                            'boolean' => true,
                        ],
                    ],
                ],
            ],
        ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function startup(): void
    {
        parent::startup();

        $storageTable = $this->params['storageTable'];

        try {
            $this->Table = TableRegistry::getTableLocator()->get($storageTable);
        } catch (Exception $e) {
            $this->abort($e->getMessage());
        }

        if (isset($this->params['limit'])) {
            if (!is_numeric($this->params['limit'])) {
                $this->abort(__('--limit must be an integer!'));
            }
            $this->limit = (int)$this->params['limit'];
        }
    }

    /**
     * Generate all image versions.
     *
     * @return void
     */
    public function regenerate(): void
    {
        if ($this->args) {
            $operations = Configure::read('FileStorage.imageVariants.' . $this->args[0]);
        } else {
            $operations = Configure::read('FileStorage.imageVariants');
        }
        $options = [
            'overwrite' => !$this->params['keep-old-versions'],
        ];

        if (!$operations) {
            $this->abort(__('Invalid table or version.'));
        }

        foreach ($operations as $version => $operation) {
            try {
                if ($this->command === null) {
                    $this->abort('No command given');
                }
                $this->_loop($this->command, $this->args[0], [$version => $operation], $options);
            } catch (Exception $e) {
                $this->abort($e->getMessage());
            }
        }
    }

    /**
     * Generate a given image version.
     *
     * @param string $model
     * @param string $collection
     *
     * @return void
     */
    public function generate(string $model, string $collection): void
    {
        $operations = Configure::read('FileStorage.imageVariants.' . $model . '.' . $collection);
        $options = [
            'overwrite' => !$this->params['keep-old-versions'],
        ];

        if (!$operations) {
            $this->out(__('Invalid table or version.'));
            $this->_stop();
        }

        try {
            $this->_loop('generate', $model, [$collection => $operations], $options);
        } catch (Exception $e) {
            $this->abort($e->getMessage());
        }
    }

    /**
     * Remove a given image version.
     *
     * @param string $model
     * @param string $version
     *
     * @return void
     */
    public function remove(string $model, string $version): void
    {
        $operations = Configure::read('FileStorage.imageVariants.' . $model . '.' . $version);

        if (!$operations) {
            $this->out(__('Invalid table or version.'));
            $this->_stop();
        }

        try {
            $this->_loop('remove', $model, [$version => $operations]);
        } catch (Exception $e) {
            $this->out($e->getMessage());
            $this->_stop();
        }
    }

    /**
     * Loops through image records and performs requested operation on them.
     *
     * @param string $action
     * @param string $model
     * @param array $operations
     * @param array<string, mixed> $options
     *
     * @return void
     */
    protected function _loop(string $action, $model, array $operations = [], array $options = []): void
    {
        if (!in_array($action, ['generate', 'remove', 'regenerate'])) {
            $this->_stop();
        }

        $totalImageCount = $this->_getCount($model);

        if ($totalImageCount === 0) {
            $this->out(__('No Images for model {0} found', $model));
            $this->_stop();
        }

        $this->out(__('{0} image file(s) will be processed' . "\n", $totalImageCount));

        $offset = 0;
        $limit = $this->limit;

        /** @var \PhpCollective\Infrastructure\Storage\FileStorage|null $storage */
        $storage = Configure::read('FileStorage.behaviorConfig.fileStorage');
        if (!$storage) {
            $this->abort(sprintf('Invalid adapter config `%s` provided!', $this->params['adapter']));
        }
        $adapter = $storage->getStorage($this->params['adapter']);

        do {
            $images = $this->_getRecords($model, $limit, $offset);
            if ($images->count()) {
                foreach ($images as $image) {
                    $payload = [
                        'entity' => $image,
                        'storage' => $adapter,
                        'operations' => $operations,
                        'versions' => array_keys($operations),
                        'table' => $this->Table,
                        'options' => $options,
                    ];

                    if ($action === 'generate' || $action === 'regenerate') {
                        $Event = new Event('ImageVersion.createVersion', $this->Table, $payload);
                        EventManager::instance()->dispatch($Event);
                    }

                    if ($action === 'remove') {
                        $Event = new Event('ImageVersion.removeVersion', $this->Table, $payload);
                        EventManager::instance()->dispatch($Event);
                    }

                    $this->out(__('{0} processed', $image->id));
                }
            }
            $offset += $limit;
        } while ($images->count() > 0);
    }

    /**
     * Gets the amount of images for a model in the DB.
     *
     * @param string $identifier
     * @param array $extensions
     *
     * @return int
     */
    protected function _getCount(string $identifier, array $extensions = ['jpg', 'png', 'jpeg']): int
    {
        return $this->Table
            ->find()
            ->where(['model' => $identifier])
            ->andWhere(['extension IN' => $extensions])
            ->count();
    }

    /**
     * Gets the chunk of records for the image processing
     *
     * @param string $identifier
     * @param int $limit
     * @param int $offset
     * @param array $extensions
     *
     * @return \Cake\Datasource\ResultSetInterface
     */
    protected function _getRecords(string $identifier, int $limit, int $offset, array $extensions = ['jpg', 'png', 'jpeg']): ResultSetInterface
    {
        return $this->Table
            ->find()
            ->where(['model' => $identifier])
            ->andWhere(['extension IN' => $extensions])
            ->limit($limit)
            ->offset($offset)
            ->all();
    }
}
