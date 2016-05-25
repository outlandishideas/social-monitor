<?php

use Phinx\Migration\AbstractMigration;

class CreateUserAccessTable extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
    	$this->execute("CREATE TABLE `user_access` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `user_id` int(11) NOT NULL,
		  `entity_type` varchar(20) NOT NULL,
		  `entity_id` int(11) NOT NULL,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `unique_index` (`user_id`,`entity_type`,`entity_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('user_access')->drop();
    }
}