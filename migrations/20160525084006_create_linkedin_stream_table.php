<?php

use Phinx\Migration\AbstractMigration;

class CreateLinkedinStreamTable extends AbstractMigration
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
    	$this->execute("CREATE TABLE `linkedin_stream` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `post_id` varchar(100) NOT NULL,
		  `presence_id` int(11) NOT NULL,
		  `message` varchar(2000) DEFAULT NULL,
		  `created_time` datetime NOT NULL,
		  `comments` int(11) NOT NULL,
		  `likes` int(11) NOT NULL,
		  `type` varchar(20) NOT NULL,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `unique_post_id` (`post_id`)
		) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=latin1");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('linkedin_stream')->drop();
    }
}