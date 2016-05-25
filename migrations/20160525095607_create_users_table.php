<?php

use Phinx\Migration\AbstractMigration;

class CreateUsersTable extends AbstractMigration
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
    	$this->execute("CREATE TABLE `users` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `name` varchar(200) NOT NULL,
		  `email` varchar(200) NOT NULL,
		  `password_hash` varchar(200) NOT NULL,
		  `twitter` varchar(20) NOT NULL,
		  `last_sign_in` datetime DEFAULT NULL,
		  `last_campaign_id` int(11) DEFAULT NULL,
		  `user_level` int(1) NOT NULL DEFAULT '1',
		  `token_id` int(11) DEFAULT NULL,
		  `reset_key` varchar(20) DEFAULT NULL,
		  `confirm_email_key` varchar(20) DEFAULT NULL,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `name` (`name`),
		  UNIQUE KEY `unique_email` (`email`)
		) ENGINE=InnoDB AUTO_INCREMENT=108 DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('users')->drop();
    }
}