<?php

use Phinx\Migration\AbstractMigration;

class UserTableEmailConfirmKey extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-change-method
     *
     * Uncomment this method if you would like to use it.
     *
    public function change()
    {
    }
    */
    
    /**
     * Migrate Up.
     */
    public function up()
    {
        $table = $this->table('users');
        $table->addColumn('confirm_email_key', 'string', [
            'after' => 'reset_key',
            'limit' => 20,
            'null' => true,
            'default' => null
        ]);
        $table->update();
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
        $table = $this->table('users');
        $table->removeColumn('confirm_email_key');
        $table->update();
    }
}