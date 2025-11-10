<?php declare(strict_types=1);

namespace FileStorage\Model\Table;

use Cake\Core\Configure;
use Cake\ORM\Table;
use Cake\Routing\Router;

/**
 * FileStorageTable
 *
 * Records in this table act as a reference to the real location of the stored
 * file data. All information of a row can be used to build a path to the file.
 * So the data in this table is pretty important.
 *
 * The reason for keeping all file references in this table is simply separation
 * of concerns: We separate the files from the other modules of the application
 * and threat them centralized and all the same.
 *
 * The actual storing and removing of the file data is handled by the Storage
 * Behavior that is attached to this table object.
 *
 * @author Florian Krämer
 * @copyright 2012 - 2020 Florian Krämer
 * @license MIT
 *
 * @method \FileStorage\Model\Entity\FileStorage newEmptyEntity()
 * @method \FileStorage\Model\Entity\FileStorage newEntity(array $data, array $options = [])
 * @method array<\FileStorage\Model\Entity\FileStorage> newEntities(array $data, array $options = [])
 * @method \FileStorage\Model\Entity\FileStorage get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \FileStorage\Model\Entity\FileStorage findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \FileStorage\Model\Entity\FileStorage patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\FileStorage\Model\Entity\FileStorage> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \FileStorage\Model\Entity\FileStorage|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \FileStorage\Model\Entity\FileStorage saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Cake\Datasource\ResultSetInterface<\FileStorage\Model\Entity\FileStorage>|false saveMany(iterable $entities, array $options = [])
 * @method \Cake\Datasource\ResultSetInterface<\FileStorage\Model\Entity\FileStorage> saveManyOrFail(iterable $entities, array $options = [])
 * @method \Cake\Datasource\ResultSetInterface<\FileStorage\Model\Entity\FileStorage>|false deleteMany(iterable $entities, array $options = [])
 * @method \Cake\Datasource\ResultSetInterface<\FileStorage\Model\Entity\FileStorage> deleteManyOrFail(iterable $entities, array $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 * @mixin \FileStorage\Model\Behavior\FileStorageBehavior
 */
class FileStorageTable extends Table
{
    /**
     * Initialize
     *
     * @param array<string, mixed> $config
     *
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('file_storage');
        $this->setPrimaryKey('id');
        $this->setDisplayField('filename');

        $this->getSchema()
            ->addColumn('variants', 'json')
            ->addColumn('metadata', 'json');

        $this->addBehavior('Timestamp');
        $this->addBehavior(
            'FileStorage.FileStorage',
            (array)Configure::read('FileStorage.behaviorConfig'),
        );
    }

    /**
     * Generate URL to file serving endpoint
     *
     * Applications must implement their own serving controller.
     * This method generates the URL to your controller's action.
     *
     * Configure the route in config/app.php:
     * ```
     * 'FileStorage' => [
     *     'serveRoute' => [
     *         'controller' => 'Files',
     *         'action' => 'serve',
     *         'plugin' => false,
     *     ],
     * ],
     * ```
     *
     * @param \FileStorage\Model\Entity\FileStorage $entity File storage entity
     * @param array<string, mixed> $options URL options (passed to Router::url)
     *
     * @return string URL to file
     */
    public function getUrl($entity, array $options = []): string
    {
        $route = Configure::read('FileStorage.serveRoute', [
            'controller' => 'Files',
            'action' => 'serve',
            'plugin' => false,
        ]);

        $url = array_merge($route, [$entity->id]);

        // Merge in query parameters
        if (isset($options['?'])) {
            $url['?'] = $options['?'];
        }

        // Check if full URL is requested
        $full = $options['_full'] ?? false;

        return Router::url($url, $full);
    }
}
