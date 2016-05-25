<?php

use Phinx\Migration\AbstractMigration;

class CreateLinkedinStreamTable extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
    	$this->execute("CREATE TABLE `linkedin_stream` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `post_id` varchar(100) NOT NULL,
		  `presence_id` int(11) NOT NULL,
		  `message` varchar(2000) DEFAULT NULL,
		  `created_time` datetime NOT NULL,
		  `comments` int(11) NOT NULL,
		  `likes` int(11) NOT NULL,
		  `type` varchar(20) NOT NULL,
		  `permalink` varchar(64) NOT NULL,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `unique_post_id` (`post_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('linkedin_stream')->drop();
    }
}