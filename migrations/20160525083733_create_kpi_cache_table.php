<?php

use Phinx\Migration\AbstractMigration;

class CreateKpiCacheTable extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
    	$this->execute("CREATE TABLE `kpi_cache` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `presence_id` int(11) NOT NULL,
		  `metric` varchar(50) NOT NULL,
		  `start_date` date NOT NULL,
		  `end_date` date NOT NULL,
		  `value` float DEFAULT NULL,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `unique_index` (`presence_id`,`metric`,`start_date`,`end_date`),
		  KEY `start_date` (`start_date`),
		  KEY `end_date` (`end_date`)
		) ENGINE=InnoDB AUTO_INCREMENT=11544167 DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('kpi_cache')->drop();
    }
}