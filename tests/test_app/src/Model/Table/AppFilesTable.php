<?php declare(strict_types=1);

namespace TestApp\Model\Table;

use FileStorage\Model\Table\FileStorageTable;

/**
 * An application subclass of the plugin table, as applications write it to add
 * their own validation sets. Cake resolves the entity class from the table class
 * name, so this one would look for a non-existent TestApp\Model\Entity\AppFile.
 */
class AppFilesTable extends FileStorageTable
{
}
