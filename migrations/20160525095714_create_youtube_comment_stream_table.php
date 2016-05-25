<?php

use Phinx\Migration\AbstractMigration;

class CreateYoutubeCommentStreamTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-change-method
     *
     * Uncomment this method if you would like to use it.
     *
    public function change()
    {
    }
    */

    /**
     * Migrate Up.
     */
    public function up()
    {
    	$this->execute("CREATE TABLE `youtube_comment_stream` (
		  `id` varchar(200) NOT NULL,
		  `presence_id` int(11) NOT NULL,
		  `video_id` varchar(200) DEFAULT NULL,
		  `message` varchar(1000) NOT NULL,
		  `in_response_to` varchar(200) DEFAULT NULL,
		  `posted_by_owner` tinyint(4) NOT NULL DEFAULT '0',
		  `author_channel_id` varchar(200) DEFAULT NULL,
		  `number_of_replies` int(11) DEFAULT NULL,
		  `likes` int(11) NOT NULL DEFAULT '0',
		  `rating` varchar(200) DEFAULT NULL,
		  `created_time` datetime NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('youtube_comment_stream')->drop();
    }
}