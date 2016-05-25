<?php

use Phinx\Migration\AbstractMigration;

class CreateUserPermissionsTable extends AbstractMigration
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
    	$this->execute("CREATE TABLE `user_permissions` (
		  `user_level` smallint(6) NOT NULL,
		  `permission` varchar(50) NOT NULL,
		  UNIQUE KEY `unique_key` (`user_level`,`permission`),
		  KEY `user_level` (`user_level`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('user_permissions')->drop();
    }
}