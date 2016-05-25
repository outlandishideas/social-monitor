<?php

use Phinx\Migration\AbstractMigration;

class CreateDomainsTable extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
    	$this->execute("CREATE TABLE `domains` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `domain` varchar(100) NOT NULL,
		  `is_bc` tinyint(1) NOT NULL DEFAULT '0',
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `domain` (`domain`)
		) ENGINE=InnoDB AUTO_INCREMENT=10018 DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('domains')->drop();
    }
}