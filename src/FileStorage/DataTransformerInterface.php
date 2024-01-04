<?php declare(strict_types=1);

namespace FileStorage\FileStorage;

use Cake\Datasource\EntityInterface;
use PhpCollective\Infrastructure\Storage\FileInterface;

interface DataTransformerInterface
{
    /**
     * @param \Cake\Datasource\EntityInterface $entity
     *
     * @return \PhpCollective\Infrastructure\Storage\FileInterface
     */
    public function entityToFileObject(EntityInterface $entity): FileInterface;

    /**
     * @param \PhpCollective\Infrastructure\Storage\FileInterface $file
     * @param \Cake\Datasource\EntityInterface|null $entity
     *
     * @return \Cake\Datasource\EntityInterface
     */
    public function fileObjectToEntity(FileInterface $file, ?EntityInterface $entity): EntityInterface;
}
