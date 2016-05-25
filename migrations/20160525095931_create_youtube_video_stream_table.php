<?php

use Phinx\Migration\AbstractMigration;

class CreateYoutubeVideoStreamTable extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
    	$this->execute("CREATE TABLE `youtube_video_stream` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `presence_id` int(11) NOT NULL,
		  `video_id` varchar(16) NOT NULL,
		  `title` text NOT NULL,
		  `description` text NOT NULL,
		  `created_time` date NOT NULL,
		  `permalink` varchar(64) NOT NULL,
		  `views` int(11) NOT NULL DEFAULT '0',
		  `likes` int(11) NOT NULL DEFAULT '0',
		  `dislikes` int(11) NOT NULL DEFAULT '0',
		  `comments` int(11) NOT NULL DEFAULT '0',
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `video_id_idx` (`video_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('youtube_video_stream')->drop();
    }
}