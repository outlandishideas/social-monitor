<?php

use Phinx\Migration\AbstractMigration;

class CreateAccessTokensTable extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
    	$this->execute("CREATE TABLE `access_tokens` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `user_id` int(11) NOT NULL,
		  `presence_type` varchar(20) NOT NULL,
		  `token` varchar(1000) NOT NULL,
		  `expires` int(11) NOT NULL,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `unique_user_type` (`user_id`,`presence_type`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('access_tokens')->drop();
    }
}