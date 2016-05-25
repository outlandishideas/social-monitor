<?php

use Phinx\Migration\AbstractMigration;

class CreateHashtagsTable extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
    	$this->execute("CREATE TABLE `hashtags` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `hashtag` varchar(100) NOT NULL,
		  `is_relevant` tinyint(1) NOT NULL DEFAULT '0',
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `hashtag` (`hashtag`)
		) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('hashtags')->drop();
    }
}