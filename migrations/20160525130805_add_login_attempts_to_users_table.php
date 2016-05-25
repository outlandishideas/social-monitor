<?php

use Phinx\Migration\AbstractMigration;

class AddLoginAttemptsToUsersTable extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('users');
        $table->addColumn('last_failed_login', 'datetime', array('null' => true))
            ->addColumn('failed_logins', 'integer', array('default' => 0, 'limit' => 1))->save();
    }

    public function down()
    {
        $table = $this->table('users');
        $table->removeColumn('last_failed_login')->removeColumn('failed_logins')->save();
    }
}