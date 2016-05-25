<?php

use Phinx\Migration\AbstractMigration;

class CreateOptionsTable extends AbstractMigration
{
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