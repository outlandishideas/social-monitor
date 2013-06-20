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

    public static function getBadgeData() {

        //set up todays date
        $date = new DateTime();
        $startDate = $date->format('Y-m-d');

        //set the types of badge data that we want to return data for (type column in table)
        $types = array('reach','engagement','quality');

        //set start and end datetimes to get all rows for todays date
        $args[':start_date'] = $startDate . ' 00:00:00';
        $args[':end_date'] = $startDate . ' 23:59:59';

        //statement returns rows with presence_id, type, value, datetime and campaign_id, ordered by campaign_id
        $sql =
            'SELECT m.presence_id, m.type, m.value, m.datetime, c.campaign_id
            FROM campaign_presences as c
            INNER JOIN (
                SELECT p.id as presence_id, ph.type, ph.value, ph.datetime
                FROM presences as p
                LEFT JOIN presence_history as ph
                ON ph.presence_id = p.id
                WHERE ph.datetime >= :start_date
                AND ph.datetime <= :end_date
                AND ph.type IN ("'. implode('","',$types) .'")
                ORDER BY p.id, p.type, ph.datetime DESC
            ) as m
            ON m.presence_id = c.presence_id
            ORDER BY c.campaign_id';

        $stmt = Zend_Registry::get('db')->prepare($sql);
        $stmt->execute($args);
        $data = $stmt->fetchAll(PDO::FETCH_OBJ);

        //if no data is returned
        if(empty($data)){

            //fetch all presences
            $presences = Model_Presence::fetchAll();
            $setHistoryArgs = array();

            //foreach presence and foreach badge, calculate the metrics and return as an object
            foreach($presences as $presence){
                foreach(Model_Presence::ALL_BADGES() as $badgeType => $metrics){
                    $setHistoryArgs[] = (array)$presence->getMetricsScore($badgeType, $metrics, $date->format('Y-m-d H-i-s'));
                }
            }

            //insert the data into the database
            Model_Base::insertData('presence_history', $setHistoryArgs);

            //fetch the newly inserted data from the database
            //do this because this is quicker than having to recreate the campaign_id data for each presence and each metric that we calculated above
            $data = self::getBadgeData();

        }

        return $data;
    }

    /**
     * organize the raw data from db into badges. Each badge is an object with score and rank properties.
     * rank property is left empty. score property is initially created as array of key($campaign_id) => value(array($presences))
     * The $array of presences is later summed to get a full score
     * @param $data
     * @return array
     */
    public function organizeBadgeData($data){

        //badgeData for campaigns is set up differently than
        $badgeData = array();

        foreach($data as $row){

            //if we haven't yet created the badge object for this type, create it
            if(!isset($badgeData[$row->type])) $badgeData[$row->type] = (object)array('score'=>array(), 'rank'=>array());

            // if we haven't tet created the entry in the score array for this campaign_id, create it
            if(!isset($badgeData[$row->type]->score[$row->campaign_id])) $badgeData[$row->type]->score[$row->campaign_id] = array();

            //if we haven't yet created the the key=>value pair for this presence_id, add it to the array for this campaign
            if(!isset($badgeData[$row->type]->score[$row->campaign_id][$row->presence_id])) $badgeData[$row->type]->score[$row->campaign_id][$row->presence_id] = $row->value;

        }

        //go through each campaign_id in each badge's score property and create the campaign score by
        //adding up the array of presences, and dividing it by the number of presences
        foreach($badgeData as $b => $badge){

                $badge->score = array_map(function($a){
                    return array_sum($a)/count($a);
                },$badge->score);

        }

        return $badgeData;
    }
}
