<?php

use Phinx\Migration\AbstractMigration;

class CreateCampaignsTable extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
    	$this->execute("CREATE TABLE `campaigns` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `display_name` varchar(200) NOT NULL,
		  `campaign_type` tinyint(1) NOT NULL DEFAULT '1',
		  `country` varchar(2) DEFAULT NULL,
		  `audience` int(11) NOT NULL DEFAULT '0',
		  `status` smallint(6) DEFAULT '0',
		  `population` int(11) DEFAULT NULL,
		  `penetration` float NOT NULL DEFAULT '0',
		  `parent` int(11) DEFAULT NULL,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `name` (`display_name`),
		  KEY `campaign_type` (`campaign_type`)
		) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('campaigns')->drop();
    }
}