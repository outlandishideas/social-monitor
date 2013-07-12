<?php

class Model_Campaign extends Model_Base {
	protected static $tableName = 'campaigns';
	protected static $sortColumn = 'display_name';

	public function delete() {
		$this->_db->prepare('DELETE FROM campaign_presences WHERE campaign_id = :cid')->execute(array(':cid'=>$this->id));
		parent::delete();
	}

	function getPresenceIds() {
		if (!isset($this->presenceIds)) {
			$statement = $this->_db->prepare('SELECT presence_id FROM campaign_presences WHERE campaign_id = :cid');
			$statement->execute(array(':cid'=>$this->id));
			$this->presenceIds = $statement->fetchAll(PDO::FETCH_COLUMN);
		}
		return $this->presenceIds;
	}

	/**
	 * @return Model_Presence[]
	 */
	function getPresences() {
		if (!isset($this->presences)) {
			$ids = $this->getPresenceIds();
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

	function getKpiData(){
		$return = array();

		// some KPIs need to be based on a date range. Use the last month's worth(?)
		$endDate = new DateTime();
		$startDate = new DateTime();
		$startDate->sub(DateInterval::createFromDateString('1 month'));

		foreach ($this->getPresences() as $presence) {
			$row = array('name'=>$presence->name, 'id'=>$presence->id);
			$row = array_merge($row, $presence->getKpiData($startDate, $endDate));
			$return[] = $row;
		}

		return $return;
	}

	function getKpiAverages() {
		if (!isset($this->kpiAverages)) {
			$scores = array();
			$metrics = Model_Presence::$ALL_METRICS;
			foreach ($metrics as $m) {
				$scores[$m] = array();
			}
			foreach ($this->getKpiData() as $p) {
				foreach ($metrics as $m) {
					if (array_key_exists($m, $p)) {
						$scores[$m][] = $p[$m];
					}
				}
			}
			$averages = array();
			foreach ($scores as $key=>$s) {
				$total = 0;
				$count = 0;
				foreach ($s as $value) {
					if ($value !== null) {
						$total += $value;
						$count++;
					}
				}
				$average = $count > 0 ? $total/$count : null;
				$averages[$key] = $average;
			}
			$this->kpiAverages = $averages;
		}

		return $this->kpiAverages;
	}

    /*****************************************************************
     * Badge Factory
     *****************************************************************/

    /**
     * organize the raw data from db into badges. Each badge is an object with score and rank properties.
     * rank property is left empty. score property is initially created as array of key($campaign_id) => value(array($presences))
     * The $array of presences is later summed to get a full score
     * @param $data
     * @return array
     */
    public static function organizeBadgeData($data){
	    $badgeData = array();

	    $badgeTypes = Model_Badge::$ALL_BADGE_TYPES;

	    foreach($data as $row){
		    //if we haven't yet created the badge object for this type, create it
		    if(!isset($badgeData[$row->daterange])) {
			    $badgeData[$row->daterange] = array();
		    }

		    // if we haven't tet created the entry in the score array for this campaign_id, create it
		    if(!isset($badgeData[$row->daterange][$row->campaign_id])) {
			    $campaignRow = array(
				    'presences' => array()
			    );
			    foreach ($badgeTypes as $type) {
				    if ($type != Model_Badge::BADGE_TYPE_TOTAL) {
					    $campaignRow[$type] = 0;
				    }
			    }
			    $badgeData[$row->daterange][$row->campaign_id] = (object)$campaignRow;
		    }

		    //if we haven't yet created the the key=>value pair for this presence_id, add it to the array for this campaign
		    if(!isset($badgeData[$row->daterange][$row->campaign_id]->presences[$row->presence_id])) {
			    $badgeData[$row->daterange][$row->campaign_id]->presences[$row->presence_id] = $row;
		    }

	    }

	    //go through each campaign_id in each badge's score property and create the campaign score by
	    //adding up the array of presences, and dividing it by the number of presences
	    foreach($badgeData as $campaigns){
		    foreach($campaigns as $campaign){
			    $presenceCount = count($campaign->presences);
			    foreach ($badgeTypes as $type) {
				    if ($type != Model_Badge::BADGE_TYPE_TOTAL) {
					    foreach($campaign->presences as $presence){
					        $campaign->$type += $presence->$type;
					    }
				        $campaign->$type /= $presenceCount;
				    }
			    }
		    }
	    }

	    foreach($badgeTypes as $type){
		    if($type != Model_Badge::BADGE_TYPE_TOTAL) {
		        foreach($badgeData as $dataRow){
	                Model_Badge::assignRanks($dataRow, $type);
                }
            }
        }

        return $badgeData;
    }
}
