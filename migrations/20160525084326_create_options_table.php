<?php

use Phinx\Migration\AbstractMigration;

class CreateOptionsTable extends AbstractMigration
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
    	$this->execute("CREATE TABLE `options` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `name` varchar(100) NOT NULL,
		  `object_id` int(11) NOT NULL DEFAULT '0',
		  `value` text NOT NULL,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `key` (`name`,`object_id`)
		) ENGINE=InnoDB AUTO_INCREMENT=14800978 DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('options')->drop();
    }
}