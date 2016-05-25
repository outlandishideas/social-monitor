<?php

use Phinx\Migration\AbstractMigration;

class CreateFacebookStreamTable extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
    	$this->execute("CREATE TABLE `facebook_stream` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `post_id` varchar(100) NOT NULL,
		  `presence_id` int(11) NOT NULL,
		  `message` varchar(1000) NOT NULL,
		  `created_time` datetime NOT NULL,
		  `actor_id` varchar(20) NOT NULL,
		  `comments` int(11) NOT NULL DEFAULT '0',
		  `likes` int(11) NOT NULL DEFAULT '0',
		  `share_count` int(11) NOT NULL DEFAULT '0',
		  `permalink` varchar(200) DEFAULT NULL,
		  `type` int(11) DEFAULT NULL,
		  `posted_by_owner` tinyint(1) NOT NULL DEFAULT '0',
		  `needs_response` tinyint(1) NOT NULL DEFAULT '0',
		  `in_response_to` varchar(100) DEFAULT NULL,
		  `bucket_half_hour` datetime DEFAULT NULL,
		  `bucket_4_hours` datetime DEFAULT NULL,
		  `bucket_12_hours` datetime DEFAULT NULL,
		  `bucket_day` datetime DEFAULT NULL,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `post_id` (`post_id`),
		  KEY `bucket_half_hour` (`bucket_half_hour`),
		  KEY `bucket_4_hours` (`bucket_4_hours`),
		  KEY `bucket_12_hours` (`bucket_12_hours`),
		  KEY `bucket_day` (`bucket_day`),
		  KEY `presence_id` (`presence_id`),
		  KEY `posted_by_owner` (`posted_by_owner`),
		  KEY `needs_response` (`needs_response`),
		  KEY `in_response_to` (`in_response_to`),
		  KEY `created_time` (`created_time`),
		  FULLTEXT KEY `message` (`message`)
		) ENGINE=InnoDB AUTO_INCREMENT=3261223 DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('facebook_stream')->drop();
    }
}