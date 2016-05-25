<?php

use Phinx\Migration\AbstractMigration;

class CreatePostsHashtagsTable extends AbstractMigration
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
    	$this->execute("CREATE TABLE `posts_hashtags` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `post` int(11) NOT NULL,
		  `hashtag` int(11) NOT NULL,
		  `post_type` varchar(20) NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('posts_hashtags')->drop();
    }
}