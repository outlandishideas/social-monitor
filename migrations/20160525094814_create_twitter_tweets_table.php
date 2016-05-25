<?php

use Phinx\Migration\AbstractMigration;

class CreateTwitterTweetsTable extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
		$this->execute("CREATE TABLE `twitter_tweets` (
		  `id` bigint(20) NOT NULL AUTO_INCREMENT,
		  `tweet_id` bigint(20) NOT NULL,
		  `presence_id` int(11) NOT NULL,
		  `text_expanded` varchar(255) NOT NULL,
		  `created_time` datetime NOT NULL,
		  `retweet_count` smallint(6) NOT NULL,
		  `html_tweet` varchar(1024) DEFAULT NULL,
		  `responsible_presence` int(11) DEFAULT NULL,
		  `needs_response` int(1) NOT NULL DEFAULT '0',
		  `permalink` varchar(64) NOT NULL,
		  `in_reply_to_user_uid` bigint(20) DEFAULT NULL,
		  `in_reply_to_status_uid` bigint(20) DEFAULT NULL,
		  `bucket_half_hour` datetime DEFAULT NULL,
		  `bucket_4_hours` datetime DEFAULT NULL,
		  `bucket_12_hours` datetime DEFAULT NULL,
		  `bucket_day` datetime DEFAULT NULL,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `unique_tweet_index` (`tweet_id`),
		  KEY `created_at` (`created_time`),
		  KEY `bucket_half_hour` (`bucket_half_hour`),
		  KEY `bucket_4_hours` (`bucket_4_hours`),
		  KEY `bucket_12_hours` (`bucket_12_hours`),
		  KEY `bucket_day` (`bucket_day`),
		  KEY `presence_id` (`presence_id`),
		  FULLTEXT KEY `text_expanded` (`text_expanded`)
		) ENGINE=InnoDB AUTO_INCREMENT=620 DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('twitter_tweets')->drop();
    }
}