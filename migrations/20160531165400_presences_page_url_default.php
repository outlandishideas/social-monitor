<?php

use Phinx\Migration\AbstractMigration;

class PresencesPageUrlDefault extends AbstractMigration
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
        $table = $this->table('presences');
        $table->changeColumn('page_url', 'string', array('limit' => 255, 'default' => ''))->update();
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
        $table = $this->table('presences');
        $table->changeColumn('page_url', 'string', array('limit' => 255))->update();
    }
}