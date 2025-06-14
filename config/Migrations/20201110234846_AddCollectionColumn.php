<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddCollectionColumn extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function change()
    {
        $this->table('file_storage')
            ->addColumn('collection', 'string', ['length' => 128, 'null' => true, 'default' => null])
            ->update();
    }
}
