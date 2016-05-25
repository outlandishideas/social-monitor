<?php

use Phinx\Migration\AbstractMigration;

class CreateStatusLinksTable extends AbstractMigration
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
    	$this->execute("CREATE TABLE `status_links` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `type` varchar(20) NOT NULL,
		  `status_id` bigint(20) NOT NULL,
		  `url` varchar(255) NOT NULL,
		  `domain` varchar(100) NOT NULL,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `type_2` (`type`,`status_id`,`url`),
		  KEY `domain` (`domain`),
		  KEY `type` (`type`,`status_id`)
		) ENGINE=InnoDB AUTO_INCREMENT=1271269 DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('status_links')->drop();
    }
}