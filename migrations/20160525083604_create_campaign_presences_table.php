<?php

use Phinx\Migration\AbstractMigration;

class CreateCampaignPresencesTable extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
    	$this->execute("CREATE TABLE `campaign_presences` (
		  `campaign_id` int(11) NOT NULL,
		  `presence_id` int(11) NOT NULL,
		  UNIQUE KEY `campaign_id` (`campaign_id`,`presence_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
		$this->table('campaign_presences')->drop();
    }
}