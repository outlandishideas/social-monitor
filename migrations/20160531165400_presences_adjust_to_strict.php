<?php

use Phinx\Migration\AbstractMigration;

class PresencesAdjustToStrict extends AbstractMigration
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
        $table->changeColumn('page_url', 'string', array('limit' => 255, 'null' => true))->update();
        $table->changeColumn('popularity', 'integer', array('limit' => 11, 'null' => true))->update();
        $table->changeColumn('sign_off', 'integer', array('limit' => 1, 'null' => true))->update();
        $table->changeColumn('branding', 'integer', array('limit' => 1, 'null' => true))->update();
        $table->changeColumn('size', 'string', array('limit' => 4, 'null' => true))->update();
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
        $table = $this->table('presences');
        $table->changeColumn('page_url', 'string', array('limit' => 255))->update();
        $table->changeColumn('popularity', 'integer', array('limit' => 11))->update();
        $table->changeColumn('sign_off', 'integer', array('limit' => 1))->update();
        $table->changeColumn('branding', 'integer', array('limit' => 1))->update();
        $table->changeColumn('size', 'string', array('limit' => 4))->update();
    }
}