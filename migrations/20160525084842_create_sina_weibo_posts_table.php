<?php

use Phinx\Migration\AbstractMigration;

class CreateSinaWeiboPostsTable extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
    	$this->execute("CREATE TABLE `sina_weibo_posts` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `remote_id` varchar(20) COLLATE utf8_bin NOT NULL,
		  `text` mediumtext COLLATE utf8_bin NOT NULL,
		  `presence_id` int(11) DEFAULT NULL,
		  `remote_user_id` varchar(20) COLLATE utf8_bin NOT NULL,
		  `created_at` datetime NOT NULL,
		  `picture_url` varchar(128) COLLATE utf8_bin DEFAULT NULL,
		  `posted_by_presence` tinyint(1) NOT NULL DEFAULT '0',
		  `included_retweet` varchar(20) COLLATE utf8_bin DEFAULT NULL,
		  `repost_count` int(11) unsigned NOT NULL DEFAULT '0',
		  `comment_count` int(11) unsigned NOT NULL DEFAULT '0',
		  `attitude_count` int(11) unsigned NOT NULL DEFAULT '0',
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `remote_id` (`remote_id`),
		  KEY `presence_id` (`presence_id`),
		  KEY `included_retweet` (`included_retweet`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('sina_weibo_posts')->drop();
    }
}