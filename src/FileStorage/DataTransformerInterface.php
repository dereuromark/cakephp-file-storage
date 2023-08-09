<?php

declare(strict_types = 1);

namespace FileStorage\FileStorage;

use Cake\Datasource\EntityInterface;
use FileStorage\Storage\FileInterface;

interface DataTransformerInterface
{
    /**
     * @param \Cake\Datasource\EntityInterface $entity
     *
     * @return \FileStorage\Storage\FileInterface
     */
    public function entityToFileObject(EntityInterface $entity): FileInterface;

    /**
     * @param \FileStorage\Storage\FileInterface $file
     * @param \Cake\Datasource\EntityInterface|null $entity
     *
     * @return \Cake\Datasource\EntityInterface
     */
    public function fileObjectToEntity(FileInterface $file, ?EntityInterface $entity): EntityInterface;
}
