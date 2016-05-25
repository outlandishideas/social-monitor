<?php

use Phinx\Migration\AbstractMigration;

class CreateStatusLinksTable extends AbstractMigration
{
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
		) ENGINE=InnoDB AUTO_INCREMENT=285 DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('status_links')->drop();
    }
}