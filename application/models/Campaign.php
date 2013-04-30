<?php

class Model_Campaign extends Model_Base {
	protected static $tableName = 'campaigns';
	protected static $sortColumn = 'display_name';

	public function delete() {
		$this->_db->prepare('DELETE FROM campaign_presences WHERE campaign_id = :cid')->execute(array(':cid'=>$this->id));
		parent::delete();
	}


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

	static function getKpis(){
		return array(
			'popularityPercentage' => 'Percent of Target Audience',
			'popularityTime' => 'Months to Target Audience',
			'postsPerDay' => 'Average Number of Posts Per Day'
		);
	}

	function getKpiData(){
		$return = array();
		foreach(static::getKpis() as $key => $kpi){
			$kpi = $this->$key;
			if($kpi){
				$return[$key] = $kpi;
			} else {
				$return[$key] = array();
			}
		}

		return $return;
	}

	function getPopularityPercentage(){
		$presences = $this->presences;
		$return = array();
		foreach($presences as $presence){
			$return[] = array('name'=>$presence->name, 'value' =>$presence->popularity);
		}
		return $return;
	}

	function getPopularityTime(){
		/** @var $presences Model_Presence[] */
		$presences = $this->presences;
		$return = array();
		$now = new DateTime();
		foreach($presences as $presence){
			$targetDate = $presence->getTargetAudienceDate($now->sub(date_interval_create_from_date_string('1 month'))->format('Y-m-d'), $now->format('Y-m-d'));
			$diff = $now->diff(new DateTime($targetDate));
			$months = $diff->m + 12*$diff->y;
			$return[] = array('name'=>$presence->name, 'value' =>$months);
		}
		return $return;
	}

    function getPostsPerDay(){
        $presences = $this->presences;
        $return = array();
        foreach($presences as $presence){
            $data = $presence->getPostsPerDayData('2013-03-27', '2013-04-09');
            foreach($data as $date){
                $return[] = array('name'=>$presence->name, 'value' => $date->post_count);
            }

        }
        return $return;
    }
}
