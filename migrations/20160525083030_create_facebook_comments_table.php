<?php

use Phinx\Migration\AbstractMigration;

class CreateFacebookCommentsTable extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
    	$this->execute("CREATE TABLE `facebook_comments` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `comment_id` varchar(100) NOT NULL,
		  `post_id` varchar(100) NOT NULL,
		  `fromid` varchar(20) NOT NULL,
		  `time` datetime NOT NULL,
		  `text` varchar(500) NOT NULL,
		  `likes` int(11) NOT NULL DEFAULT '0',
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `comment_id` (`comment_id`),
		  KEY `post_id` (`post_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('facebook_comments')->drop();
    }
}