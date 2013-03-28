<?php

class Model_Campaign extends Model_Base {
	protected static $tableName = 'campaigns';
	protected static $sortColumn = 'name';

    function getPresences() {
	    if (!isset($this->presences)) {
		    $statement = $this->_db->prepare('SELECT presence_id FROM campaign_presences WHERE campaign_id = :cid');
		    $statement->execute(array(':cid'=>$this->id));
		    $ids = $statement->fetchAll(PDO::FETCH_COLUMN);
		    if ($ids) {
			    $clause = 'id IN (' . implode(',', $ids) . ')';
			    $this->presences = Model_Presence::fetchAll($clause);
		    } else {
			    $this->presences = array();
		    }
	    }
	    return $this->presences;
    }

	function assignPresences($ids) {
		$this->_db->prepare('DELETE FROM campaign_presences WHERE campaign_id = :cid')->execute(array(':cid'=>$this->id));
		if ($ids) {
			$toInsert = array();
			foreach ($ids as $id) {
				$toInsert[] = array('campaign_id'=>$this->id, 'presence_id'=>$id);
			}
			$this->insertData('campaign_presences', $toInsert);
		}
	}

	function getFacebookPages() {
		return array_filter($this->getPresences(), function($a) { return $a->type == 'facebook'; });
	}

	function getTwitterAccounts() {
		return array_filter($this->getPresences(), function($a) { return $a->type == 'twitter'; });
	}
}
