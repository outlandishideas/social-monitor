<?php

use Phinx\Migration\AbstractMigration;

class CreateTwitterUsersTable extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
    	$this->execute("CREATE TABLE `twitter_users` (
		  `id` bigint(20) NOT NULL,
		  `name` varchar(100) NOT NULL,
		  `description` varchar(200) DEFAULT NULL,
		  `statuses_count` int(11) NOT NULL,
		  `profile_image_url` varchar(200) NOT NULL,
		  `followers_count` int(11) NOT NULL,
		  `screen_name` varchar(20) NOT NULL,
		  `url` varchar(200) DEFAULT NULL,
		  `friends_count` int(11) NOT NULL,
		  `location_id` varchar(200) NOT NULL,
		  `peerindex` int(11) DEFAULT NULL,
		  `peerindex_last_updated` datetime DEFAULT NULL,
		  `klout` float DEFAULT NULL,
		  `klout_last_updated` datetime DEFAULT NULL,
		  `followers_last_updated` datetime DEFAULT NULL,
		  `friends_last_updated` datetime DEFAULT NULL,
		  `user_last_updated` datetime DEFAULT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('twitter_users')->drop();
    }
}