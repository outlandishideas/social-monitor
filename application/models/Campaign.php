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
     * function gets returns rows for all Badge data stored in the presence_history for today's date
     * If badge data is not yet in the table for today, it will calculate it and insert it and then return it
     * @param int
     * @return array
     */
    public static function getBadgeData($startDate = null, $endDate = null) {

        $class = get_called_class();
        $countItems = $class::countAll();

        if(!$startDate || !$endDate){
            //get today's date
            $endDate = new DateTime();
            $startDate = clone $endDate;
        }


        $clauses = array();

        //start and end dateTimes return all entries from today's date
        $clauses[] = 'p.datetime >= :start_date';
        $clauses[] = 'p.datetime <= :end_date';
        $args[':start_date'] = $startDate->format('Y-m-d') . ' 00:00:00';
        $args[':end_date'] = $endDate->format('Y-m-d') . ' 23:59:59';

        //returns rows with presence_id, type, value and datetime, ordered by presence_id, type, and datetime(DESC)
        $sql =
            'SELECT p.*, c.campaign_id
            FROM badge_history as p
            INNER JOIN campaign_presences as c
            ON p.presence_id = c.presence_id
            WHERE '.implode(" AND ", $clauses).'
            ORDER BY p.presence_id, p.datetime DESC';

        $stmt = Zend_Registry::get('db')->prepare($sql);
        $stmt->execute($args);
        $data = $stmt->fetchAll(PDO::FETCH_OBJ);

        //if too few rows are returned
        if(empty($data)){

            self::calculatePresenceBadgeData($data, $endDate);

            $data = self::getBadgeData();
        }

        return $data;
    }

    public static function organizeBadges($data){
        //badgeData for campaigns is set up differently than
        $badgeData = array();

        foreach($data as $row){

            //if we haven't yet created the badge object for this type, create it
            if(!isset($badgeData[$row->type])) $badgeData[$row->type] = array();

            // if we haven't tet created the entry in the score array for this campaign_id, create it
            if(!isset($badgeData[$row->type][$row->campaign_id])) {
                $badgeData[$row->type][$row->campaign_id] = clone $row;
                unset($badgeData[$row->type][$row->campaign_id]->presence_id);
                $badgeData[$row->type][$row->campaign_id]->presences = array();
            }

            //if we haven't yet created the the key=>value pair for this presence_id, add it to the array for this campaign
            if(!isset($badgeData[$row->type][$row->campaign_id]->presences[$row->presence_id])) $badgeData[$row->type][$row->campaign_id]->presences[$row->presence_id] = $row;

        }

        //go through each campaign_id in each badge's score property and create the campaign score by
        //adding up the array of presences, and dividing it by the number of presences
        foreach($badgeData as $t => $type){

            foreach($type as $c => $campaign){

                $campaign->reach = 0;
                $campaign->engagement = 0;
                $campaign->quality = 0;

                $countPresences = count($campaign->presences);

                foreach($campaign->presences as $p => $presence){

                    $campaign->reach += $presence->reach;
                    $campaign->engagement += $presence->engagement;
                    $campaign->quality += $presence->quality;

                }

                $campaign->reach /= $countPresences;
                $campaign->engagement /= $countPresences;
                $campaign->quality /= $countPresences;

            }
        }
        return $badgeData;
    }

    /**
     * organize the raw data from db into badges. Each badge is an object with score and rank properties.
     * rank property is left empty. score property is initially created as array of key($campaign_id) => value(array($presences))
     * The $array of presences is later summed to get a full score
     * @param $data
     * @return array
     */
    public static function organizeBadgeData($data){

        $badgeData = self::organizeBadges($data);

        foreach($badgeData as $t => $type){

            foreach(Model_Badge::ALL_BADGES_TITLE() as $badge => $metric){

                if($badge == Model_Badge::METRIC_BADGE_TOTAL) continue;

                usort($type, function($a, $b) use ($badge){
                    if($a->$badge == $b->$badge) return 0;
                    return $a->$badge > $b->$badge ? -1 : 1;
                });

                $lastScore = null;
                $ranking = 1;
                foreach($type as $row) {
                    //if score is not equal to last score increase ranking by 1
                    if(is_numeric($lastScore) && $lastScore != $row->$badge){
                        $ranking++;
                    }

                    $rankType = $badge.'_rank';

                    $row->$rankType = $ranking;

                    //set current score to $lastScore for next value in array
                    $lastScore = $row->$badge;
                }
            }

        }

        return $badgeData;
    }
}
