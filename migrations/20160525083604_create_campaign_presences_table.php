<?php

use Phinx\Migration\AbstractMigration;

class CreateCampaignPresencesTable extends AbstractMigration
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