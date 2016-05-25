<?php

use Phinx\Migration\AbstractMigration;

class CreateUserJoyridesTable extends AbstractMigration
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
    	$this->execute("CREATE TABLE `user_joyrides` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `user_id` int(11) DEFAULT NULL,
		  `joyride` varchar(20) DEFAULT NULL,
		  `been_ridden` smallint(6) DEFAULT '0',
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `user_joyrides_user_id_joyride_uindex` (`user_id`,`joyride`)
		) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=latin1");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('user_joyrides')->drop();
    }
}