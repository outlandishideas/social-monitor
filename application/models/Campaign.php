<?php

class Model_Campaign extends Model_Base {
	protected static $tableName = 'campaigns';
	protected static $sortColumn = 'name';

    function getPresences() {
        $this->presences = Model_Presence::fetchAll('campaign_id = :cid', array(':cid'=>$this->id));
        return $this->presences;
    }

	function getFacebookPages() {
		return array_filter($this->getPresences(), function($a) { return $a->type == 'facebook'; });
	}

	function getTwitterAccounts() {
		return array_filter($this->getPresences(), function($a) { return $a->type == 'twitter'; });
	}
}
