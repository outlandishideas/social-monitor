<?php

use Phinx\Migration\AbstractMigration;

class CreateInstagramStreamTable extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
    	$this->execute("CREATE TABLE `instagram_stream` (
		  `id` int(11) NOT NULL,
		  `post_id` varchar(100) NOT NULL,
		  `presence_id` int(11) NOT NULL,
		  `message` varchar(1000) NOT NULL,
		  `image_url` varchar(200) NOT NULL,
		  `created_time` datetime NOT NULL,
		  `comments` int(11) NOT NULL,
		  `likes` int(11) NOT NULL,
		  `share_count` int(11) NOT NULL DEFAULT '0',
		  `permalink` varchar(200) NOT NULL,
		  `bucket_half_hour` datetime DEFAULT NULL,
		  `bucket_4_hours` datetime DEFAULT NULL,
		  `bucket_12_hours` datetime DEFAULT NULL,
		  `bucket_day` datetime DEFAULT NULL
		) ENGINE=InnoDB DEFAULT CHARSET=latin1");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('instagram_stream')->drop();
    }
}