<?php
use Outlandish\SocialMonitor\Cache\KpiCacheEntry;
use Outlandish\SocialMonitor\PresenceType\PresenceType;

/**
 * @property int audience
 */
class Model_Campaign extends Model_Base {
	protected static $tableName = 'campaigns';
	protected static $sortColumn = 'display_name';
	protected static $badges = array();

	// use this to filter campaigns table by campaign_type column
	protected static $campaignType = null;

	/**
	 * @param $presenceId
	 * @return Model_Campaign|null
	 */
	public static function fetchOwner($presenceId) {
		$stmt = self::$db->prepare('SELECT campaign_id FROM campaign_presences WHERE presence_id = :pid');
		$stmt->execute(array(':pid'=>$presenceId));
		$campaignId = $stmt->fetchColumn(0);
		if($campaignId === false) {
			return null;
		} else {
			return Model_Campaign::fetchById($campaignId);
		}
	}

	public function getPresencesBySize($size)
	{
		return array_filter($this->getPresences(), function (Model_Presence $presence) use ($size) {
			return $presence->getSize() == $size;
		});
	}

	protected function fetch($clause = null, $args = array()) {
		$type = self::campaignType();
		if($type !== null) {
			if ($clause) {
				$clause .= ' AND ';
			}
			$clause .= ' campaign_type = ' . $type;
		}
		return parent::fetch($clause, $args);
	}

	/**
	 * @param $data
	 * @return Model_Base[]
	 */
	public static function objectify($data) {
		$objects = array();
		$campaignClasses = Model_Campaign::campaignClasses();
		foreach ($data as $row) {
			foreach ($campaignClasses as $campaignClass) {
				if ($row['campaign_type'] == $campaignClass::campaignType()) {
					$objects[] = new $campaignClass($row, true);
					break;
				}
			}
		}
		return $objects;
	}

	/**
	 * Get the target audience value for this campaign
	 *
	 * A campaign can be given a target audience value. This value is used to determine
	 * the target audience for a presence.
	 *
	 * @return int
	 */
	public function getTargetAudience() {
		$target = $this->audience;

		if (!is_numeric($target)) {
			$target = 0;
		}

		return intval($target);
	}

	protected function count($clause = null, $args = array()) {
		if ($clause) {
			$clause .= ' AND ';
		}
		$clause .= ' campaign_type = ' . self::campaignType();
		return parent::count($clause, $args);
	}


	public function delete() {
		$this->_db->prepare('DELETE FROM campaign_presences WHERE campaign_id = :cid')->execute(array(':cid'=>$this->id));
		parent::delete();
	}

	function getPresenceCount() {
		return count($this->getPresenceIds());
	}

	function getPresenceIds($mapping = null) {
		if (!isset($this->presenceIds)) {
			if (isset($mapping[$this->id])) {
				$this->presenceIds = $mapping[$this->id];
			} else {
				$statement = $this->_db->prepare('SELECT presence_id FROM campaign_presences WHERE campaign_id = :cid');
				$statement->execute(array(':cid'=>$this->id));
				$this->presenceIds = $statement->fetchAll(\PDO::FETCH_COLUMN);
			}
		}
		return $this->presenceIds;
	}

	/**
	 * Gets the presences for this campaign. If $mapping and $allPresences are present, they will be used instead
	 * of doing a database query
	 * @param array $mapping
	 * @param Model_Presence[] $allPresences
	 * @return Model_Presence[]
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
					$this->presences = Model_PresenceFactory::getPresencesById($ids);
				}
			}
		}
		return $this->presences;
	}

	/**
	 * @param PresenceType $type
	 * @return Model_Presence[]
	 */
	public function getPresencesByType(PresenceType $type){
		$presences = $this->getPresences();
		return array_filter($presences, function(Model_Presence $a) use ($type) {
			return $a->getType() == $type;
		});
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

	function getKpiData(){
		$return = array();

		// some KPIs need to be based on a date range. Use the last month's worth(?)
		$endDate = new DateTime();
		$startDate = new DateTime();
		$startDate->sub(DateInterval::createFromDateString('30 days'));

		foreach ($this->getPresences() as $presence) {
			$row = array('name'=>$presence->name, 'id'=>$presence->id);
			$row = array_merge($row, $presence->getKpiData(new KpiCacheEntry($startDate, $endDate)));
			$return[] = $row;
		}

		return $return;
	}

	/*****************************************************************
	 * Badge Factory
	 *****************************************************************/

	public function getBadges()
	{
		$allBadges = self::getAllBadges();
		if(array_key_exists($this->id, $allBadges)){
			return $allBadges[$this->id];
		} else {
			return null;
		}
	}

	public function getBadgeHistory(DateTime $start, DateTime $end)
	{
		$presenceIds = $this->getPresenceIds();
		if (!$presenceIds) {
			return array();
		}
		return Badge_Factory::getAllCurrentData(Enum_Period::MONTH(), $start, $end, $presenceIds);
	}

	/**
	 * Very similar to Model_Presence::getAllBadges, but presences are grouped and keyed by campaign ID
	 * @return array
	 */
	public static function getAllBadges()
	{
		$key = get_called_class();
		if(empty(static::$badges[$key])){
			$campaignIds = array();
			foreach (static::fetchAll() as $campaign) {
				$campaignIds[] = $campaign->id;
			};

			$badgeData = Badge_Factory::badgesData();
			$badgeNames = Badge_Factory::getBadgeNames();

            $totalBadgeName = Badge_Total::NAME;
			$keyedData = array();
			foreach ($badgeData as $row) {
				$toAdd = array($row->campaign_id, $row->region_id);
				foreach ($toAdd as $campaignId) {
					if(in_array($campaignId, $campaignIds)) {
						if(!array_key_exists($campaignId, $keyedData)){
							$keyedData[$campaignId] = array('presences' => 0, 'denominator' => count($campaignIds));
							foreach($badgeNames as $badgeName){
								$keyedData[$campaignId][$badgeName] = 0;
							}
						}
						foreach($badgeNames as $badgeName){
							if($badgeName != $totalBadgeName) {
								$keyedData[$campaignId][$badgeName] += $row->$badgeName;
							}
						}
						$keyedData[$campaignId]['presences']++;
					}
				}
			}

			foreach($keyedData as &$campaignData){
				foreach($badgeNames as $badgeName){
					if($badgeName != $totalBadgeName) {
						//get average for kpi scores by dividing by number of presences
						$campaignData[$badgeName] /= $campaignData['presences'];
						//add average to total score
						$campaignData[$totalBadgeName] += $campaignData[$badgeName];
					}
				}
				//divide the total score by the number of badges (-1 for the total badge)
				$campaignData[$totalBadgeName] /= count($badgeNames) - 1;
				unset($campaignData['presences']);
			}

			foreach($badgeNames as $badgeName){
				Badge_Abstract::doRanking($keyedData, $badgeName, $badgeName . '_rank');
				//colorize
				foreach($keyedData as $id=>&$row) {
					if (array_key_exists($badgeName, $row)){
						$row[$badgeName . '_color'] = Badge_Abstract::colorize($row[$badgeName]);
					}
				}
			}
			static::$badges[$key] = $keyedData;
		}

		return static::$badges[$key];
	}

	/**
	 * Gets the SQL for getting the campaign<->presence mapping
	 * @return string
	 */
	protected static function mappingSql() {
		return '
            SELECT
              c.id AS campaign_id,
              cp.presence_id
            FROM
              campaigns AS c
              LEFT OUTER JOIN campaign_presences AS cp ON cp.campaign_id = c.id
            WHERE
              c.campaign_type = :campaign_type';
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
		if ($data) {
			foreach ($data as $row) {
				$campaignIds[$row->campaign_id] = 1;
				if (!$maxDate || $row->date > $maxDate) {
					$maxDate = $row->date;
				}
			}
		}

		if (!$campaignIds) {
			return array();
		}

		$maxDate = new DateTime($maxDate);

		$badgeTypes = Badge_Factory::getBadges();

		$campaigns = array();

		/** @var Model_Campaign[] $campaignData */
		$campaignData = static::fetchAll('id IN (' . implode(',', array_filter(array_keys($campaignIds))) . ')');
		foreach ($campaignData as $campaign) {
			$row = (object)array(
				'id'=>intval($campaign->id),
				'c' => $campaign->country,
				'n' => $campaign->display_name,
				'p' => $campaign->getPresenceCount(),
				'b' => new stdClass()
			);

			// add data structures for keeping scores in
			foreach ($badgeTypes as $type) {
				if ((!$type instanceof Badge_Total)) {
                    $typeName = $type->getName();
					$row->b->$typeName = array();
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
			$campaign->b->{Badge_Total::NAME} = $total;
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

	static function campaignType() {
		return static::$campaignType;
	}

	/**
	 * Gets all of the valid subclasses of campaign, in priority order
	 * @return self[]
	 */
	static function campaignClasses() {
		return array('Model_Country', 'Model_Group', 'Model_Region');
	}

	/**
	 * Returns the summed popularity of all the presences for this campaign
	 *
	 * @return int
	 */
	public function getPopularity()
	{
		return array_reduce($this->getPresences(), function($carry, Model_Presence $presence) {
			$carry += $presence->getPopularity();
			return $carry;
		}, 0);
	}

	public function getName() {
		return $this->display_name;
	}

	public function getRegion()
	{
		return null;
	}
}
