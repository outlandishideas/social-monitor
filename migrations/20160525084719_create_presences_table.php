<?php

use Phinx\Migration\AbstractMigration;

class CreatePresencesTable extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
    	$this->execute("CREATE TABLE `presences` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `type` varchar(20) NOT NULL,
		  `handle` varchar(100) NOT NULL,
		  `uid` varchar(100) NOT NULL,
		  `image_url` varchar(255) NOT NULL,
		  `name` varchar(100) NOT NULL,
		  `page_url` varchar(255) NOT NULL,
		  `popularity` int(11) NOT NULL DEFAULT '0',
		  `sign_off` tinyint(1) NOT NULL DEFAULT '0',
		  `branding` tinyint(1) NOT NULL DEFAULT '0',
		  `klout_id` varchar(40) DEFAULT NULL,
		  `klout_score` float DEFAULT NULL,
		  `facebook_engagement` float DEFAULT NULL,
		  `instagram_engagement` float DEFAULT NULL,
		  `sina_weibo_engagement` float DEFAULT NULL,
		  `size` tinyint(4) NOT NULL DEFAULT '0',
		  `user_id` int(11) DEFAULT NULL,
		  `last_fetched` datetime DEFAULT NULL,
		  `last_updated` datetime DEFAULT NULL,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `type` (`type`,`handle`)
		) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('presences')->drop();
    }
}