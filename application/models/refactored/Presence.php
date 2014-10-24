<?php

class NewModel_Presence
{
	protected $provider;
	protected $db;
	protected $metrics;
	protected static $badges = array();
	protected $kpiData = array();

	protected $presenceHistoryColumns = array(
		'popularity', 'klout_score', 'facebook_engagement'
	);

	//these should be public to mimic existing Presence Class
	public $id;
	public $handle;
    /** @var NewModel_PresenceType */
	public $type;
	public $name;
	public $label;
	public $uid;
	public $sign_off;
	public $branding;
	public $popularity;
	public $klout_id;
	public $klout_score;
	public $facebook_engagement;
	public $page_url;
	public $image_url;
	public $owner;
	public $last_updated;
	public $last_fetched;

    /**
     * Creates a new presence
     * Provider, metrics and badges are passed in so that they can be mocked out for testing
     * todo: only pass in a (mockable) type instead?
     * @param PDO $db
     * @param array $internals
     * @param NewModel_iProvider $provider
     * @param array $metrics
     * @param array $badges
     * @throws InvalidArgumentException
     */
    public function __construct(PDO $db, array $internals, NewModel_iProvider $provider, array $metrics = array(), array $badges = array())
	{
		$this->db = $db;
		$this->provider = $provider;
		$this->metrics = $metrics;
//		$this->badges = $badges;

		if (!array_key_exists('id', $internals)) {
			throw new \InvalidArgumentException('Missing id for Presence');
		}
		if (!array_key_exists('handle', $internals)) {
			throw new \InvalidArgumentException('Missing handle for Presence');
		}
		$this->id = $internals['id'];
        $this->handle = $internals['handle'];
        $this->setType($internals['type']);
        $this->name = $internals['name'];
        $this->uid = $internals['uid'];
        $this->sign_off = $internals['sign_off'];
        $this->branding = $internals['branding'];
        $this->popularity = $internals['popularity'];
        $this->klout_score = $internals['klout_score'];
        $this->facebook_engagement = $internals['facebook_engagement'];
        $this->page_url = $internals['page_url'];
        $this->image_url = $internals['image_url'];
        $this->last_updated = $internals['last_updated'];
        $this->last_fetched = $internals['last_fetched'];
    }

	public function getId()
	{
		return $this->id;
	}

	/**
	 * @return Metric_Abstract[]
	 */
	public function getMetrics()
	{
		return $this->metrics;
	}

	public function getLastFetched()
	{
		return $this->last_fetched;
	}

	public function getLastUpdated()
	{
		return $this->last_updated;
	}

	public function getHandle()
	{
		return $this->handle;
	}

	public function getType()
	{
		return $this->type;
	}

	public function getCharts()
	{
		return array(
			Chart_Compare::getName() => Chart_Compare::getTitle()
		);
	}

	public function setType($typeName)
	{
        $this->type = NewModel_PresenceType::get($typeName);
	}

	public function getName()
	{
		return $this->name;
	}

	public function getPageUrl()
	{
		return $this->page_url;
	}

	public function getImageUrl()
	{
		return $this->page_url;
	}

	public function getLabel() {
		return $this->getName() ?: $this->getHandle();
	}

	public function getUID()
	{
		return $this->uid;
	}

	public function getSignOff()
	{
		return $this->sign_off;
	}

	public function getBranding()
	{
		return $this->branding;
	}

	public function getKloutId()
	{
		return $this->klout_id;
	}

	public function getKloutScore()
	{
		return $this->klout_score;
	}

	public function getPopularity()
	{
		return $this->popularity;
	}

	/**
	 * @return mixed
	 */
	public function getFacebookEngagement()
	{
		return $this->facebook_engagement;
	}

	/**
	 * @return mixed
	 */
	public function getPresenceSign()
	{
		return $this->getType()->getSign();
	}

	/**
	 * @return bool
	 */
	public function isForTwitter()
	{
		return $this->getType()->getValue() == NewModel_PresenceType::TWITTER;
	}

	public function isForFacebook()
	{
		return $this->getType()->getValue() == NewModel_PresenceType::FACEBOOK;
	}

	public function getOwner()
	{
		if(!$this->owner){
			$stmt = $this->db->prepare('SELECT campaign_id FROM campaign_presences WHERE presence_id = :pid');
			$stmt->execute(array(':pid'=>$this->getId()));
			$campaignId = $stmt->fetchColumn(0);
			if($campaignId === false) return null;
			Model_Base::setDb($this->db);
			$this->owner = Model_Campaign::fetchById($campaignId);
		}
		return $this->owner;
	}

	public function getTargetAudience()
	{
		if($this->getType() != NewModel_PresenceType::SINA_WEIBO()){
			$presence = Model_Presence::fetchById($this->getId());
			return $presence->getTargetAudience();
		}
		$target = null;
		$owner = $this->getOwner();
		if($owner){
			$target = $owner->getTargetAudience();
			$target *= BaseController::getOption($this->isForFacebook() ? 'fb_min' : 'tw_min');
			$target /= 100;
		}
		return $target;
	}


	/**
	 * Gets the date at which the target audience size will be reached, based on the trend over the given time period.
	 * If the target is already reached, or there is no target, this will return null.
	 * If any of these conditions are met, this will return the maximum date possible:
	 * - popularity has never varied
	 * - the calculated date is in the past
	 * - there are fewer than 2 data points
	 * - the calculated date would be too far in the future (32-bit date problem)
	 * @param DateTime $start
	 * @param DateTime $end
	 * @return null|DateTime
	 */
	public function getTargetAudienceDate(DateTime $start, DateTime $end)
	{
		$date = new DateTime; //the return value

		$target = $this->getTargetAudience();
		$popularity = $this->getPopularity();
		if(is_numeric($target) && $target > 0 && $popularity < $target) {

			$data = $this->getHistoricData($start, $end);

			if(count($data) > 0) {

				//remove any non-popularity values
				$cleanData = array_filter($data, function($row){
					return $row['type'] == 'popularity';
				});

				$count = count($cleanData);

				if ($count > 1) {
					// calculate line of best fit (see http://www.endmemo.com/statistics/lr.php)
					$meanX = $meanY = $sumXY = $sumXX = 0;

					foreach ($data as $row) {
						$rowDate = $start->diff(new DateTime($row['datetime']))->days; //use days instead of timestamps to prevent overflowing
						$meanX += $rowDate;
						$meanY += $row['value'];
						$sumXY += $rowDate*$row['value'];
						$sumXX += $rowDate*$rowDate;
					}

					$meanX /= $count;
					$meanY /= $count;

                    $denominator = ($sumXX - $count*$meanX*$meanX);
                    $numerator = ($sumXY - $count*$meanX*$meanY);

					if ($denominator != 0 && $numerator/$denominator > 0) {
                        $a = $numerator/$denominator;
                        $b = $meanY - $a*$meanX;
						$daysNeeded = ceil(($target - $b)/$a);

						//we've been having some difficulties with DateTime and
						//large numbers. Try to run a DateTime construct to see if it works
						//if not nullify $date so that we can create a DateTime from PHP_INI_MAX
						try {
							$date = clone $start;
							$date->modify("+ $daysNeeded days");
						} catch (Exception $e) {
							$date = null;
						}
					}
				}

			}

			if (!($date instanceof DateTime) || $date->getTimestamp() < time()) {
				try {
					$date = new DateTime(PHP_INT_MAX);
				} catch (Exception $e) {
					$date = null;
				}
			}
		}

		if ($date instanceof DateTime) {
			$date->setTime(0,0,0);
		}
		return $date;
	}

	public function getRatioRepliesToOthersPosts(\DateTime $startDate, \DateTime $endDate)
	{
		$clauses = array(
			'presence_id = :pid',
			'created_time >= :start_date',
			'created_time <= :end_date'
		);

		if (!$this->isForFacebook()) {
			return null;
		}

		$tableName = $this->provider->getTableName();
		$sql = '
		SELECT t1.replies/t2.posts as replies_to_others_posts FROM
		(
			SELECT presence_id, COUNT(*) as replies
			FROM ' . $tableName . '
			WHERE ' . implode(' AND ', $clauses) .'
			AND in_response_to IS NOT NULL
		) as t1,
		(
			SELECT presence_id, COUNT(*) as posts
			FROM ' . $tableName . '
			WHERE ' . implode(' AND ', $clauses) .'
			AND posted_by_owner = 0
		) as t2';
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array(
			':pid'			=> $this->id,
			':start_date'	=> $startDate->format('Y-m-d H:i:s'),
			':end_date'		=> $endDate->format('Y-m-d H:i:s')
		));
		return floatval($stmt->fetchColumn());
	}

	public function getKpiData(DateTime $start = null, DateTime $end = null, $useCache = true)
	{
		// if($this->getType() != NewModel_PresenceType::SINA_WEIBO()){
		// 	$presence = Model_Presence::fetchById($this->getId());
		// 	return $presence->getKpiData($start, $end, $useCache);
		// }

		if (!$start || !$end) {
			$end = new DateTime();
			$start = clone $end;
			$start->sub(DateInterval::createFromDateString('1 month'));
		}

		$endString = $end->format('Y-m-d');
		$startString = $start->format('Y-m-d');
		$key = $startString . $endString;

		if(!array_key_exists($key, $this->kpiData)){

			$cachedValues = array();
			if ($useCache) {
				$cachedValues = $this->getCachedKpiData($start, $end);
			}

			foreach($this->getMetrics() as $metric){
				if(!array_key_exists($metric->getName(), $cachedValues)){
					$cachedValues[$metric->getName()] = $metric->calculate($this, $start, $end);
				}
			}

			$this->updateKpiData($key, $cachedValues);
		}

		return $this->kpiData[$key];
	}

	public function getCachedKpiData(DateTime $start, DateTime $end)
	{
		$kpis = array();
		$stmt = $this->db->prepare(
			"SELECT metric, value FROM `kpi_cache`
				WHERE `presence_id` = :pid
				AND `start_date` = :start
				AND `end_date` = :end");
		$stmt->execute(array(
			':pid' => $this->getId(),
			':start' => $start->format("Y-m-d"),
			':end' => $end->format("Y-m-d")
		));
		$kpis = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
		return $kpis;
	}

	public function updateKpiData($key, $value)
	{
		$this->kpiData[$key] = $value;
	}

	public function getHistoricData(\DateTime $start, \DateTime $end)
	{
		return $this->provider->getHistoricData($this, $start, $end);
	}

	public function getHistoricStream(\DateTime $start, \DateTime $end)
	{
		return $this->provider->getHistoricStream($this, $start, $end);
	}

	public function getHistoricStreamMeta(\DateTime $start, \DateTime $end)
	{
		return $this->provider->getHistoricStreamMeta($this, $start, $end);
	}

	public function fetch()
	{
		$this->provider->fetchStatusData($this);
        $this->last_fetched = gmdate('Y-m-d H:i:s');
        $this->save();
	}

    /**
     * method for updating a presence's info
     * if successful we also update the presence_history table with the latest info
     *
     * @return array|null
     */
	public function update()
	{
        $this->provider->update($this);
	}

    public function updateHistory() {
        $date = gmdate('Y-m-d H:i:s');
        //if the presence was updated, update presence_history
        $stmt = $this->db->prepare("INSERT INTO `presence_history` (`presence_id`, `datetime`, `type`, `value`) VALUES (:id, :datetime, :type, :value)");
        foreach($this->presenceHistoryColumns as $type){
            $value = $this->$type;
            if (!is_null($value)) {
                $stmt->execute(array(
                        ':id' => $this->getId(),
                        ':datetime' => $date,
                        ':type'	=> $type,
                        ':value' => $value
                    ));
            }
        }
    }

	public function saveMetric($metric, DateTime $start, DateTime $end, $value){
		$stmt = $this->db->prepare("
			INSERT INTO `kpi_cache`
				(`presence_id`, `metric`, `start_date`, `end_date`, `value`)
			VALUES
				(:id, :metric, :start, :end, :value)
			ON DUPLICATE KEY UPDATE
				`value` = VALUES(`value`)
		");
		$stmt->execute(array(
			':id' => $this->getId(),
			':metric' => $metric,
			':start' => $start->format("Y-m-d"),
			':end' => $end->format("Y-m-d"),
			':value' => $value
		));
	}

	public function saveBadgeResult($result, \DateTime $date, Badge_Period $range, $badgeName)
	{
		$stmt = $this->db->prepare("SELECT `id` FROM `badge_history` WHERE `presence_id` = :id AND `date` = :date AND `daterange` = :range");
		$stmt->execute(array(
			':id'	=> $this->getId(),
			':date'	=> $date->format('Y-m-d'),
			':range'=> (string) $range
		));
		$id = $stmt->fetchColumn(0);
		if (false === $id) {
			$stmt = $this->db->prepare("INSERT INTO `badge_history` (`presence_id`, `daterange`, `date`, `{$badgeName}`) VALUES (?, ?, ?, ?)");
			$stmt->execute(array($this->getId(), (string) $range, $date->format('Y-m-d'), $result));
		} else {
			$stmt = $this->db->prepare("UPDATE `badge_history` SET `{$badgeName}` = :result WHERE `id` = :id");
			$stmt->execute(array(':result' => $result, ':id' => $id));
		}
	}

    public function getBadgeScores(DateTime $date, Badge_Period $range) {
        if (!$date || !$range) {
            throw new LogicException('date cannot be null');
        }
        $args = array(
            'id' => $this->getId(),
            'date' => $date->format('Y-m-d'),
            'range' => (string)$range
        );
        $stmt = $this->db->prepare("SELECT * FROM `badge_history` WHERE `presence_id` = :id AND `date` = :date AND `daterange` = :range");
        $stmt->execute($args);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($results) {
            return $results[0];
        }
        return null;
    }

	public function getBadges()
	{
		$badges = static::getAllBadges();
        if (isset($badges[$this->getId()])) {
            return $badges[$this->getId()];
        }
        return null;
	}

	public function getBadgeHistory(DateTime $start, DateTime $end)
	{
		return Badge_Factory::getAllCurrentData(Badge_Period::MONTH(), $start, $end, array($this->getId()));
	}

	public static function getAllBadges()
	{
		if(empty(static::$badges)){
			$data =  Badge_Factory::badgesData(true);
			$badgeNames = Badge_Factory::getBadgeNames();

            $keyedData = array();
			foreach($data as $presenceData){
				$presenceData[Badge_Total::getName()] = 0;
				foreach($badgeNames as $name){
					if($name != Badge_Total::getName()) {
                        //add average to total score
                        $presenceData[Badge_Total::getName()] += $presenceData[$name];
                    }
				}
				//divide the total score by the number of badges (-1 for the totalbadge)
				$presenceData[Badge_Total::getName()] /= count($badgeNames) - 1;
				$presenceData['denominator'] = count($data);
                $keyedData[$presenceData['presence_id']] = $presenceData;
			}

            $name = Badge_Total::getName();
			Badge_Abstract::doRanking($keyedData, $name, $name . '_rank');

			static::$badges = $keyedData;
		}
		return static::$badges;
	}

	/**
	 * DEPRECATED: Use getHistoricStream() instead
	 * @param $start
	 * @param $end
	 * @param $search
	 * @param $order
	 * @param $limit
	 * @param $offset
	 * @return array
	 */
	public function getStatuses(DateTime $start, DateTime $end, $search, $order, $limit, $offset)
	{
//		trigger_error("Deprecated function called.", E_USER_NOTICE);
		if($this->getType() != NewModel_PresenceType::SINA_WEIBO()){
			$presence = Model_Presence::fetchById($this->getId());
			return $presence->getStatuses($start->format("Y-m-d"), $end->format("Y-m-d"), $search, $order, $limit, $offset);
		}

		return $this->getHistoricStream($start, $end);
	}

	/**
	 * DEPRECATED: Use getHistoricData()
	 * @param DateTime $start
	 * @param DateTime $end
	 * @return array
	 */
	public function getPopularityData(DateTime $start, DateTime $end){
//		trigger_error("Deprecated function called.", E_USER_NOTICE);
		if($this->getType() != NewModel_PresenceType::SINA_WEIBO()){
			$presence = Model_Presence::fetchById($this->getId());
			return $presence->getPopularityData($start->format("Y-m-d"), $end->format("Y-m-d"));
		}
		$data = $this->getHistoricData($start, $end);
		return array_filter($data, function($row){
			return $row['type'] == Metric_Popularity::getName();
		});
	}

	/**
	 * @param DateTime $start
	 * @param DateTime $end
	 * @return mixed
	 */
	public function getPostsPerDayData(DateTime $start, DateTime $end)
	{
//		trigger_error("Deprecated function called.", E_USER_NOTICE);
		if($this->getType() != NewModel_PresenceType::SINA_WEIBO()){
			$presence = Model_Presence::fetchById($this->getId());
			return $presence->getPostsPerDayData($start->format("Y-m-d"), $end->format("Y-m-d"));
		}
	}

	/**
	 * @param DateTime $start
	 * @param DateTime $end
	 * @return mixed
	 */
	public function getRelevanceData(DateTime $start, DateTime $end)
	{
//		trigger_error("Deprecated function called.", E_USER_NOTICE);
		if($this->getType() != NewModel_PresenceType::SINA_WEIBO()){
			$presence = Model_Presence::fetchById($this->getId());
			return $presence->getRelevanceData($start->format("Y-m-d"), $end->format("Y-m-d"));
		}
	}

	/**
	 * @param DateTime $start
	 * @param DateTime $end
	 * @return mixed
	 */
	public function getResponseData(DateTime $start, DateTime $end)
	{
//		trigger_error("Deprecated function called.", E_USER_NOTICE);
		if($this->getType() != NewModel_PresenceType::SINA_WEIBO()){
			$presence = Model_Presence::fetchById($this->getId());
			return $presence->getResponseData($start->format("Y-m-d"), $end->format("Y-m-d"));
		}
	}

	/**
	 * @param bool $includeBreakdown
	 * @return array
	 */
	public function badges($includeBreakdown = true)
	{
//		trigger_error("Deprecated function called.", E_USER_NOTICE);
		if($this->getType() != NewModel_PresenceType::SINA_WEIBO()){
			$presence = Model_Presence::fetchById($this->getId());
			return $presence->badges($includeBreakdown);
		}
	}

	public function save()
	{
        if (!$this->id) {
            return;
        }

        $data = array(
            'type' => $this->type->getValue(),
            'handle' => $this->handle,
            'uid' => $this->uid,
            'image_url' => $this->image_url,
            'name' => $this->name,
            'page_url' => $this->page_url,
            'popularity' => $this->popularity,
            'klout_id' => $this->klout_id,
            'klout_score' => $this->klout_score,
            'facebook_engagement' => $this->facebook_engagement,
            'last_updated' => $this->last_updated,
            'sign_off' => $this->sign_off,
            'branding' => $this->branding
        );

        $query = 'UPDATE '.NewModel_PresenceFactory::TABLE_PRESENCES.' '.
            'SET '.implode('=?, ', array_keys($data)).'=? '.
            'WHERE id=?';
        //add id to fill last placeholder
        $data[] = $this->id;

        $statement = $this->db->prepare($query);
        $statement->execute(array_values($data));
	}



}