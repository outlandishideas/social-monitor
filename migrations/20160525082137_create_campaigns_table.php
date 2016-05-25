<?php

use Phinx\Migration\AbstractMigration;

class CreateCampaignsTable extends AbstractMigration
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
    	$this->execute("CREATE TABLE `campaigns` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `display_name` varchar(200) NOT NULL,
		  `is_country` tinyint(1) NOT NULL DEFAULT '1',
		  `campaign_type` tinyint(1) NOT NULL DEFAULT '1',
		  `country` varchar(2) NOT NULL,
		  `audience` int(11) NOT NULL,
		  `status` smallint(6) DEFAULT '0',
		  `population` int(11) DEFAULT NULL,
		  `penetration` float NOT NULL,
		  `parent` int(11) NOT NULL,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `name` (`display_name`),
		  KEY `campaign_type` (`campaign_type`)
		) ENGINE=InnoDB AUTO_INCREMENT=285 DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('campaigns')->drop();
    }
}