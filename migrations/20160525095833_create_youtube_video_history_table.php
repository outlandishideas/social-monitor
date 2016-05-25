<?php

use Phinx\Migration\AbstractMigration;

class CreateYoutubeVideoHistoryTable extends AbstractMigration
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
    	$this->execute("CREATE TABLE `youtube_video_history` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `video_id` int(11) NOT NULL,
		  `datetime` date NOT NULL,
		  `type` varchar(16) NOT NULL,
		  `value` int(11) NOT NULL,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `video_id` (`video_id`,`datetime`,`type`)
		) ENGINE=InnoDB AUTO_INCREMENT=66513 DEFAULT CHARSET=latin1");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('youtube_video_history')->drop();
    }
}