<?php declare(strict_types=1);

namespace FileStorage\Model\Behavior;

use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Association\HasMany;
use Cake\ORM\Association\HasOne;
use Cake\ORM\Behavior;
use Laminas\Diactoros\UploadedFile;

/**
 * File Association Behavior.
 *
 * @author Florian Krämer
 * @copyright 2012 - 2020 Florian Krämer
 * @license MIT
 */
class FileAssociationBehavior extends Behavior
{
    /**
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'associations' => [],
    ];

    /**
     * @inheritDoc
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $model = $this->table()->getAlias();
        foreach ($config['associations'] as $association => $assocConfig) {
            $associationObject = $this->table()->getAssociation($association);

            $defaults = [
                'replace' => $associationObject instanceof HasOne,
                'model' => $model,
                'collection' => $association,
                'property' => $this->table()->getAssociation($association)->getProperty(),
            ];

            $config['associations'][$association] = $assocConfig + $defaults;
        }

        $this->setConfig('associations', $config['associations']);
    }

    /**
     * @param \Cake\Event\EventInterface $event
     * @param \Cake\Datasource\EntityInterface $entity
     * @param \ArrayObject $options
     *
     * @return void
     */
    public function beforeSave(
        EventInterface $event,
        EntityInterface $entity,
        ArrayObject $options,
    ): void {
        $associations = $this->getConfig('associations');
        foreach ($associations as $assocConfig) {
            $property = $assocConfig['property'];
            if ($entity->{$property} === null) {
                continue;
            }

            $association = $this->table()->{$assocConfig['collection']};
            if ($association instanceof HasOne) {
                if (is_array($entity->{$property})) {
                    $entity->{$property} = $this->table()->{$assocConfig['collection']}->newEntity($entity->{$property});
                }

                $entity->{$property}->set('model', $assocConfig['model']);
                $entity->{$property}->set('collection', $assocConfig['collection']);
            } elseif ($association instanceof HasMany) {
                foreach ($entity->{$property} as &$v) {
                    if (is_array($v)) {
                        $v = $this->table()->{$assocConfig['collection']}->newEntity($v);
                    }

                    $v->set('model', $assocConfig['model']);
                    $v->set('collection', $assocConfig['collection']);
                }
            }
        }
    }

    /**
     * @param \Cake\Event\EventInterface $event
     * @param \Cake\Datasource\EntityInterface $entity
     * @param \ArrayObject $options
     *
     * @return void
     */
    public function afterSave(
        EventInterface $event,
        EntityInterface $entity,
        ArrayObject $options,
    ): void {
        $associations = $this->getConfig('associations');
        foreach ($associations as $assocName => $assocConfig) {
            $property = $assocConfig['property'];
            if ($entity->{$property} === null) {
                continue;
            }
            $association = $this->table()->{$assocConfig['collection']};

            if (!$entity->get('id') || !$entity->{$property}) {
                continue;
            }

            if ($association instanceof HasOne) {
                $this->processOne($entity, $assocName, $assocConfig);

                return;
            }

            $this->processMany($entity, $assocName, $assocConfig);
        }
    }

    /**
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $assocName
     * @param array $assocConfig
     *
     * @return void
     */
    protected function processOne(EntityInterface $entity, string $assocName, array $assocConfig)
    {
        $property = $assocConfig['property'];

        $fileEntity = $entity->{$property};
        if (!$fileEntity->file) {
            return;
        }

        $file = $fileEntity->file;

        $ok = false;
        if (is_array($file) && $file['error'] === UPLOAD_ERR_OK) {
            $ok = true;
        } elseif ($file instanceof UploadedFile && $file->getError() === UPLOAD_ERR_OK) {
            $ok = true;
        }

        if (!$ok) {
            return;
        }

        $fileEntity->set('foreign_key', $entity->get('id'));
        $this->table()->{$assocName}->saveOrFail($fileEntity);

        if ($assocConfig['replace'] === true) {
            $this->findAndRemovePreviousFile($entity, $assocName, $assocConfig);
        }
    }

    /**
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $assocName
     * @param array $assocConfig
     *
     * @return void
     */
    protected function processMany(EntityInterface $entity, string $assocName, array $assocConfig)
    {
        $property = $assocConfig['property'];

        foreach ($entity->{$property} as &$fileEntity) {
            if (!$fileEntity->file) {
                continue;
            }

            $file = $fileEntity->file;

            $ok = false;
            if (is_array($file) && $file['error'] === UPLOAD_ERR_OK) {
                $ok = true;
            } elseif ($file instanceof UploadedFile && $file->getError() === UPLOAD_ERR_OK) {
                $ok = true;
            }

            if (!$ok) {
                continue;
            }

            $fileEntity->set('foreign_key', $entity->get('id'));
            $this->table()->{$assocName}->saveOrFail($fileEntity);

            if ($assocConfig['replace'] === true) {
                $this->findAndRemovePreviousFile($entity, $assocName, $assocConfig);
            }
        }
    }

    /**
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $association
     * @param array $assocConfig
     *
     * @return void
     */
    protected function findAndRemovePreviousFile(
        EntityInterface $entity,
        string $association,
        array $assocConfig,
    ): void {
        /** @var \FileStorage\Model\Entity\FileStorage $fileEntity */
        $fileEntity = $entity->get($assocConfig['property']);

        /** @var string $key */
        $key = $this->table()->{$association}->getPrimaryKey();
        /** @var string $foreignKey */
        $foreignKey = $this->table()->getPrimaryKey();
        $entities = $this->table()->{$association}->find()->where(
            [
                'model' => $assocConfig['model'],
                'collection' => $assocConfig['collection'] ?? null,
                'foreign_key' => $entity->get($foreignKey),
                'id !=' => $fileEntity->get($key),
            ],
        )->all()->toArray();
        foreach ($entities as $entity) {
            $this->table()->{$association}->delete($entity);
        }
    }
}
