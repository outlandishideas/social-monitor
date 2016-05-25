<?php

use Phinx\Migration\AbstractMigration;

class CreatePresenceHistoryTable extends AbstractMigration
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
    	$this->execute("CREATE TABLE `presence_history` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `presence_id` int(11) NOT NULL,
		  `datetime` datetime NOT NULL,
		  `type` varchar(100) NOT NULL,
		  `value` float NOT NULL,
		  PRIMARY KEY (`id`),
		  KEY `type` (`type`),
		  KEY `datetime` (`datetime`),
		  KEY `presence_id` (`presence_id`)
		) ENGINE=InnoDB AUTO_INCREMENT=1862164 DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('presence_history')->drop();
    }
}