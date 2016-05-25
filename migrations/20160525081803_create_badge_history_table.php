<?php

use Phinx\Migration\AbstractMigration;

class CreateBadgeHistoryTable extends AbstractMigration
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
    	$this->execute("CREATE TABLE `badge_history` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `presence_id` int(11) NOT NULL,
		  `daterange` varchar(10) NOT NULL,
		  `reach` int(11) DEFAULT NULL,
		  `engagement` int(11) DEFAULT NULL,
		  `quality` int(11) DEFAULT NULL,
		  `reach_rank` int(11) DEFAULT NULL,
		  `engagement_rank` int(11) DEFAULT NULL,
		  `quality_rank` int(11) DEFAULT NULL,
		  `date` date NOT NULL,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `unique_index` (`date`,`daterange`,`presence_id`),
		  KEY `presence_id` (`presence_id`),
		  KEY `daterange` (`daterange`)
		) ENGINE=InnoDB AUTO_INCREMENT=103101 DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('badge_history')->drop();
    }
}