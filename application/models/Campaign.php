<?php

class Model_Campaign extends Model_Base {
	protected static $tableName = 'campaigns';
	protected static $sortColumn = 'display_name';

	// use this to filter campaigns table by is_country column
	public static $countryFilter = null;

	protected function fetch($clause = null, $args = array()) {
		if( static::$countryFilter !== null) {
			if ($clause) {
				$clause .= ' AND ';
			}
			$clause .= ' is_country = ' . static::$countryFilter;
		}
		return parent::fetch($clause, $args);
	}

	/**
	 * @param $data
	 * @return Model_Base[]
	 */
	public static function objectify($data) {
		$objects = array();
		foreach ($data as $row) {
			if($row['is_country'] == 1){
				$objects[] = new Model_Country($row, true);
			} else {
				$objects[] = new Model_Group($row, true);
			}
		}
		return $objects;
	}

    public function getTargetAudience() {
        return $this->audience;
    }

	protected function count($clause = null, $args = array()) {
		if ($clause) {
			$clause .= ' AND ';
		}
		$clause .= ' is_country = ' . static::$countryFilter;
		return parent::count($clause, $args);
	}


	public function delete() {
		$this->_db->prepare('DELETE FROM campaign_presences WHERE campaign_id = :cid')->execute(array(':cid'=>$this->id));
		parent::delete();
	}

	function getPresenceCount() {
		$statement = $this->_db->prepare('SELECT COUNT(1) FROM campaign_presences WHERE campaign_id = :cid');
		$statement->execute(array(':cid'=>$this->id));
		return intval($statement->fetchColumn());
	}

	function getPresenceIds($mapping = null) {
		if (!isset($this->presenceIds)) {
			if (isset($mapping[$this->id])) {
				$this->presenceIds = $mapping[$this->id];
			} else {
				$statement = $this->_db->prepare('SELECT presence_id FROM campaign_presences WHERE campaign_id = :cid');
				$statement->execute(array(':cid'=>$this->id));
				$this->presenceIds = $statement->fetchAll(PDO::FETCH_COLUMN);
			}
		}
		return $this->presenceIds;
	}

	/**
	 * Gets the presences for this campaign. If $mapping and $allPresences are present, they will be used instead
	 * of doing a database query
	 * @param array $mapping
	 * @param Model_Presence[] $allPresences
	 * @return NewModel_Presence[]
	 */
	function getPresences($mapping = null, $allPresences = null) {
		if (!isset($this->presences)) {
			$this->presences = array();
			$ids = $this->getPresenceIds($mapping);
			if ($ids) {
				if ($allPresences) {
					foreach ($ids as $id) {
						if (isset($allPresences[$id])) {
							$this->presences[] = $allPresences[$id];
						}
					}
				} else {
					$this->presences = NewModel_PresenceFactory::getPresencesById($ids);
				}
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
	 * Gets the badges for this country/group
	 * @return array
	 */
	public function badges(){
		$badgeTypes = Model_Badge::$ALL_BADGE_TYPES;

        $allCampaigns = static::badgesData();

		$campaignCount = count($allCampaigns);
		$badges = array();
		$badgeData = $allCampaigns[$this->id];
		$presences = $this->getPresences();
		foreach ($badgeTypes as $badgeType) {
			$badge = array(
				'type'=>$badgeType,
				'score'=>floatval($badgeData->{$badgeType}),
				'rank'=>intval($badgeData->{$badgeType.'_rank'}),
				'rankTotal'=>$campaignCount,
				'metrics'=>array()
			);
			// average the individual metric scores
			foreach ($presences as $presence) {
				$metrics = $presence->getMetrics($badgeType);
				foreach ($metrics as $m=>$metric) {
					if (!isset($badge['metrics'][$m])) {
						$badge['metrics'][$m] = (object)array(
							'score' => 0,
							'type' => $m,
							'title' => $metric->title
						);
					}
					$badge['metrics'][$m]->score += $metric->score;
				}
			}
			foreach($badge['metrics'] as $metric){
				$metric->score /= count($presences);
			}
			$badges[$badgeType] = (object)$badge;
		}

		return $badges;
	}

    public static function badgesData(){
        $badgeTypes = Model_Badge::$ALL_BADGE_TYPES;
        $keyedData = Model_Badge::badgesData(true);

        // get all of the campaign-presence relationships for this type (country or group)
	    /** @var PDO $db */
        $db = Zend_Registry::get('db')->getConnection();
        $stmt = $db->prepare(
            'SELECT c.id AS campaign_id, cp.presence_id
            FROM campaigns AS c
            LEFT OUTER JOIN campaign_presences AS cp
                ON cp.campaign_id = c.id
            WHERE c.is_country = :is_country');
	    $args = array(':is_country'=>static::$countryFilter);
        $stmt->execute($args);
        $mapping = $stmt->fetchAll(PDO::FETCH_OBJ);

        // calculate averages badge scores for each campaign
        $allCampaigns = array();
        $template = array('count'=>0);
        foreach ($badgeTypes as $badgeType) {
            $template[$badgeType] = 0;
        }
        foreach ($mapping as $row) {
            if (!isset($allCampaigns[$row->campaign_id])) {
                $campaign = (object)$template;
                $allCampaigns[$row->campaign_id] = $campaign;
            } else {
                $campaign = $allCampaigns[$row->campaign_id];
            }
            if (array_key_exists($row->presence_id, $keyedData)) {
                $campaign->count++;
                foreach ($badgeTypes as $badgeType) {
                    if ($badgeType != Model_Badge::BADGE_TYPE_TOTAL) {
                        $campaign->$badgeType += $keyedData[$row->presence_id]->$badgeType;
                    }
                }
            }
        }
        foreach ($allCampaigns as $campaign) {
            if ($campaign->count > 0) {
                foreach ($badgeTypes as $badgeType) {
                    $campaign->$badgeType /= $campaign->count;
                }
            }
        }

        // calculate the total scores for each campaign, and calculate ranks for all badge types
        foreach ($allCampaigns as $campaign) {
            Model_Badge::calculateTotalScore($campaign);
        }
        foreach ($badgeTypes as $badgeType) {
            Model_Badge::assignRanks($allCampaigns, $badgeType);
        }
        return $allCampaigns;
    }

	/**
	 * Creates a structure containing data for $dayRange days worth of badge data for all of this type of campaign
	 * @param $data
	 * @param $dayRange
	 * @return array
	 */
	public static function constructFrontPageData($data, $dayRange){
		$campaignIds = array();
		$maxDate = null;
		foreach ($data as $row) {
			$campaignIds[$row->campaign_id] = 1;
			if (!$maxDate || $row->date > $maxDate) {
				$maxDate = $row->date;
			}
		}
		$maxDate = new DateTime($maxDate);

		$badgeTypes = Model_Badge::$ALL_BADGE_TYPES;

		$campaigns = array();

		/** @var Model_Campaign $campaign */
		foreach (static::fetchAll('id IN (' . implode(',', array_filter(array_keys($campaignIds))) . ')') as $campaign) {
			$row = (object)array(
				'id'=>intval($campaign->id),
				'c' => $campaign->country,
				'n' => $campaign->display_name,
				'p' => $campaign->getPresenceCount(),
				'b' => new stdClass()
			);

			// add data structures for keeping scores in
			foreach ($badgeTypes as $type) {
				if ($type != Model_Badge::BADGE_TYPE_TOTAL) {
					$row->b->$type = array();
				}
			}
			$campaigns[$campaign->id] = $row;
		}

		$daysList = array();
		//now that we have campaign objects set up, go though the data and assign it to the appropriate object
		foreach ($data as $row) {
			if (array_key_exists($row->campaign_id, $campaigns)){
				$campaign = $campaigns[$row->campaign_id];
				//calculate the number of days since this row of data was created
				$rowDate = new DateTime($row->date);
				$rowDiff = $rowDate->diff($maxDate);
				//turn it around so that the most recent data is the has the highest score
				//this is because jquery slider has a value going 0-30 (left to right) and we want time to go in reverse
				$days = $dayRange - $rowDiff->days;
				$daysList[$days] = 1;

				foreach ($campaign->b as $badgeType=>$ignored) {
					if(!isset($campaign->b->{$badgeType}[$days])) {
						$campaign->b->{$badgeType}[$days] = $row->$badgeType;
					} else {
						$campaign->b->{$badgeType}[$days] += $row->$badgeType;
					}
				}
			}
		}

		//calculate the total scores for each day for each campaign object
		foreach ($campaigns as $campaign) {
			$badgeCount = 0;
			$total = array();
			//go though each day in each badge in each campaign and convert the score into an score/label object for geochart
			foreach ($campaign->b as $badgeType => $ignored){
				$badgeCount++;
				foreach ($campaign->b->$badgeType as $day => $value){
					$value /= $campaign->p; //average out the score
					$campaign->b->{$badgeType}[$day] = (object)array('s'=>round($value*10)/10, 'l'=>round($value).'%');
					if(!isset($total[$day])) {
						$total[$day] = $value;
					} else {
						$total[$day] += $value;
					}
				}
			}

			foreach ($total as $day => $value) {
				$value /= $badgeCount; // average out the badges
				$total[$day] = (object)array('s'=>round($value*10)/10, 'l'=>round($value).'%');
			}
			$campaign->b->{Model_Badge::BADGE_TYPE_TOTAL} = $total;
		}

		// fill in any holes by copying the closest day
		$daysList = array_keys($daysList);
		foreach ($campaigns as $campaign) {
			foreach ($campaign->b as $badgeType=>$days) {
				$missing = array_diff($daysList, array_keys($days));
				foreach ($missing as $day) {
					$key = '';
					for ($i=1; $i<30; $i++) {
						if (array_key_exists($day-$i, $days)) {
							$key = $day-$i;
							break;
						} else if (array_key_exists($day+$i, $days)) {
							$key = $day+$i;
							break;
						}
					}
					if ($key) {
						$campaign->b->{$badgeType}[$day] = $campaign->b->{$badgeType}[$key];
					}
				}
			}
		}

		return array_values($campaigns);
	}

}
