<?php

use Phinx\Migration\AbstractMigration;

class CreateFacebookActorsTable extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
    	$this->execute("CREATE TABLE `facebook_actors` (
		  `id` varchar(20) NOT NULL,
		  `username` varchar(100) DEFAULT NULL,
		  `name` varchar(100) DEFAULT NULL,
		  `pic_url` varchar(255) DEFAULT NULL,
		  `profile_url` varchar(255) DEFAULT NULL,
		  `type` varchar(10) NOT NULL,
		  `last_fetched` datetime NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('facebook_actors')->drop();
    }
}