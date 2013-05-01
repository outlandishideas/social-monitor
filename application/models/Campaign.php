<?php

class Model_Campaign extends Model_Base {
	protected static $tableName = 'campaigns';
	protected static $sortColumn = 'display_name';

	const KPI_POPULARITY_PERCENTAGE = 'popularityPercentage';
	const KPI_POPULARITY_TIME = 'popularityTime';
	const KPI_POSTS_PER_DAY = 'postsPerDay';

	public function delete() {
		$this->_db->prepare('DELETE FROM campaign_presences WHERE campaign_id = :cid')->execute(array(':cid'=>$this->id));
		parent::delete();
	}


	/**
	 * @return Model_Presence[]
	 */
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
			self::KPI_POPULARITY_PERCENTAGE => 'Percent of Target Audience',
			self::KPI_POPULARITY_TIME => 'Time to Target Audience',
			self::KPI_POSTS_PER_DAY => 'Average Number of Posts Per Day'
		);
	}

	function getKpiData(){
		$return = array();

		// some KPIs need to be based on a date range. Use the last month's worth(?)
		$now = new DateTime();
		$nowString = $now->format('Y-m-d');
		$date = new DateTime();
		$date->sub(DateInterval::createFromDateString('1 month'));
		$monthAgo = $date->format('Y-m-d');

		$kpis = static::getKpis();
		foreach ($this->getPresences() as $presence) {
			$row = array('name'=>$presence->name, 'id'=>$presence->id);
			foreach($kpis as $key => $label){
				switch ($key) {
					case self::KPI_POPULARITY_PERCENTAGE:
						$target = $presence->getTargetAudience();
						$row[$key] = $target ? min(100, 100*$presence->popularity/$target) : 100;
						$row[$key . '-target'] = $target;
						break;
					case self::KPI_POPULARITY_TIME:
						$target = $presence->getTargetAudience();
						if ($presence->popularity >= $target) {
							$row[$key] = 0; // already achieved
						} else {
							$targetDate = $presence->getTargetAudienceDate($monthAgo, $nowString);
							if ($targetDate) {
								$diff = $now->diff(new DateTime($targetDate));
								$months = $diff->m + 12*$diff->y;
								$row[$key] = $months;
							}
						}
						break;
					case self::KPI_POSTS_PER_DAY:
						$row[$key] = $presence->getAveragePostsPerDay($monthAgo, $nowString);
						break;
				}
			}
			$return[] = $row;
		}

		return $return;
	}

}
