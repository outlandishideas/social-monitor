<?php

use Phinx\Migration\AbstractMigration;

class CreateObjectCacheTable extends AbstractMigration
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
    	$this->execute("CREATE TABLE `object_cache` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `key` varchar(255) CHARACTER SET latin1 NOT NULL,
		  `value` mediumblob NOT NULL,
		  `temporary` tinyint(4) NOT NULL DEFAULT '0',
		  `last_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `key` (`key`,`last_modified`)
		) ENGINE=InnoDB AUTO_INCREMENT=99412 DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('object_cache')->drop();
    }
}