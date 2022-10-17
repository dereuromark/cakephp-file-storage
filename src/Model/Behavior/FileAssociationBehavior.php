<?php

declare(strict_types = 1);

namespace FileStorage\Model\Behavior;

use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
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
    protected $_defaultConfig = [
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
             /* e.g.
                'EventImages' => [
                    'collection' => 'EventImages',
                    'replace' => true,
                    'model' => 'Events',
                    'property' => 'event_image',
                 ],
             */

            $config['associations'][$association] = $assocConfig + $defaults;

            if ($associationObject instanceof HasOne) {
                // Let's create a tmp assoc on the fly for saving/creating
                $this->table()->hasOne($association . 'New', [
                    'className' => 'FileStorage.FileStorage',
                    'foreignKey' => 'foreign_key',
                    'conditions' => [
                        $association . 'New.model' => 'Events',
                    ],
                    'joinType' => 'LEFT',
                ]);

                $associationTmp = $config['associations'][$association];
                $associationTmp['collection'] .= 'New';
                $associationTmp['property'] .= '_new';
                $associationTmp['link'] = $association;
                $config['associations'][$association . 'New'] = $associationTmp;
            }
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
        ArrayObject $options
    ): void {
        $associations = $this->getConfig('associations');
        foreach ($associations as $assocConfig) {
            $property = $assocConfig['property'];
            if ($entity->{$property} === null) {
                continue;
            }

            if (is_array($entity->{$property})) {
                $entity->{$property} = $this->table()->{$assocConfig['collection']}->newEntity($entity->{$property});
            }

            $entity->{$property}->set('collection', $assocConfig['collection']);
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
        ArrayObject $options
    ): void {
        $associations = $this->getConfig('associations');
        foreach ($associations as $association => $assocConfig) {
            $linkedAssoc = $assocConfig['link'] ?? $association;
            $linkedAssocConfig = $associations[$linkedAssoc];
            $property = $assocConfig['property'];
            if ($entity->{$property} === null) {
                continue;
            }

            if ($entity->id && $entity->{$property} && $entity->{$property}->file) {
                $file = $entity->{$property}->file;

                $ok = false;
                if (is_array($file) && $file['error'] === UPLOAD_ERR_OK) {
                    $ok = true;
                } elseif ($file instanceof UploadedFile && $file->getError() === UPLOAD_ERR_OK) {
                    $ok = true;
                }

                if (!$ok) {
                    continue;
                }

                $entity->{$property}->set('collection', $linkedAssocConfig['collection']);
                $entity->{$property}->set('model', $linkedAssocConfig['model']);
                $entity->{$property}->set('foreign_key', $entity->id);

                $this->table()->{$association}->saveOrFail($entity->{$property});

                if ($assocConfig['replace'] === true) {
                    $this->findAndRemovePreviousFile($entity, $association, $linkedAssocConfig);
                }
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
        array $assocConfig
    ): void {
        // Remove suffix
        $newEntity = $entity->get($assocConfig['property'] . '_new') ?: $entity->get($association['property']);

        // Use existing assoc
        $existingAssociation = substr($association, -3) === 'New' ? substr($association, 0, -3) : $association;

        $this->table()->{$association}->deleteAll(
            [
                'model' => $assocConfig['model'],
                'collection' => $assocConfig['collection'] ?? null,
                'foreign_key' => $entity->get((string)$this->table()->getPrimaryKey()),
                'id !=' => $newEntity->get((string)$this->table()->{$existingAssociation}->getPrimaryKey()),
            ],
        );
    }
}
