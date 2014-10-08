<?php

class NewModel_Presence
{
	protected $provider;
	protected $db;
	protected $metrics;
	protected $kpiData = array();

	//these should be public to mimic existing Presence Class
	public $id;
	public $handle;
	public $type;
	public $label;
	public $uid;
	public $sign_off;
	public $branding;
	public $popularity;
	public $klout_score;
	public $facebook_engagement;
	public $page_url;
	public $image_url;
	public $owner;

	public function __construct(PDO $db, array $internals, NewModel_iProvider $provider, array $metrics = array())
	{
		$this->db = $db;
		$this->provider = $provider;
		$this->metrics = $metrics;

		if (!array_key_exists('id', $internals)) {
			throw new \InvalidArgumentException('Missing id for Presence');
		}
		if (!array_key_exists('handle', $internals)) {
			throw new \InvalidArgumentException('Missing handle for Presence');
		}
		$this->id = $internals['id'];
		$this->handle = $internals['handle'];
		$this->type = $internals['type'];
		$this->label = $internals['name'];
		$this->uid = $internals['uid'];
		$this->sign_off = $internals['sign_off'];
		$this->branding = $internals['branding'];
		$this->popularity = $internals['popularity'];
		$this->klout_score = $internals['klout_score'];
		$this->facebook_engagement = $internals['facebook_engagement'];
		$this->page_url = $internals['page_url'];
		$this->image_url = $internals['image_url'];
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

	public function getHandle()
	{
		return $this->handle;
	}

	public function getType()
	{
		return $this->type;
	}

	public function getLabel()
	{
		return $this->label;
	}

	public function getPageUrl()
	{
		return $this->page_url;
	}

	public function getImageUrl()
	{
		return $this->page_url;
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

	public function getKloutScore()
	{
		return $this->klout_score;
	}

	public function getPopularity()
	{
		return $this->popularity;
	}

	public function getFacebookEngagement()
	{
		return $this->facebook_engagement;
	}

	public function getPresenceSign()
	{
		return $this->provider->getSign();
	}

	public function isForTwitter()
	{
		return $this->getType() == NewModel_PresenceType::TWITTER();
	}

	public function isForFacebook()
	{
		return $this->getType() == NewModel_PresenceType::FACEBOOK();
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
		$date = null; //the return value

		$target = $this->getTargetAudience();
		$popularity = $this->getPopularity();
		if(is_numeric($target) && $target > 0 && $popularity < $target) {

			$data = $this->getHistoricData($start, $end);

			//remove any non-popularity values
			$cleanData = array_filter($data, function($row){
				return $row['type'] == 'popularity';
			});

			$count = count($cleanData);

			if ($count > 1) {
				// calculate line of best fit (see http://www.endmemo.com/statistics/lr.php)
				$meanX = $meanY = $sumXY = $sumXX = 0;

				foreach ($data as $row) {
					$row->datetime = strtotime($row->datetime);
					$meanX += $row->datetime;
					$meanY += $row->value;
					$sumXY += $row->datetime*$row->value;
					$sumXX += $row->datetime*$row->datetime;
				}

				$meanX /= $count;
				$meanY /= $count;

				$a = ($sumXY - $count*$meanX*$meanY)/($sumXX - $count*$meanX*$meanX);
				$b = $meanY - $a*$meanX;

				if ($a > 0) {
					$timestamp = ($target - $b)/$a;
					if ($timestamp < PHP_INT_MAX) {

						//we've been having some difficulties with DateTime and
						//large numbers. Try to run a DateTime construct to see if it works
						//if not nullify $date so that we can create a DateTime from PHP_INI_MAX
						try {
							$date = new DateTime($timestamp);
						} catch (Exception $e) {
							$date = null;
						}

					}
				}
			}

			if (!($date instanceof DateTime) || $date->getTimestamp() < time()) {
				$date = new DateTime(PHP_INT_MAX);
			}
		}
		return $date;
	}

	public function getKpiData(DateTime $start = null, DateTime $end = null, $useCache = true)
	{
		if($this->getType() != NewModel_PresenceType::SINA_WEIBO()){
			$presence = Model_Presence::fetchById($this->getId());
			return $presence->getKpiData($start, $end, $useCache);
		}

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
			"SELECT * FROM `kpi_cache`
				WHERE `presence_id` = :pid
				AND `start_date` = :start
				AND `end_date` = :end");
		$stmt->execute(array(
			':pid' => $this->getId(),
			':start' => $start->format("Y-m-d"),
			':end' => $end->format("Y-m-d")
		));
		$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach($results as $row){
			$kpis[$row['metric']] = $row['value'];
		}
		return $kpis;
	}

	public function updateKpiData($key, $value)
	{
		$this->kpiData[$key] = $value;
	}

	public function getHistoricData(\DateTime $start, \DateTime $end) {
		return $this->provider->getHistoricData($this, $start, $end);
	}

	public function getHistoricStream(\DateTime $start, \DateTime $end) {
		return $this->provider->getHistoricStream($this, $start, $end);
	}

	public function getHistoricStreamMeta(\DateTime $start, \DateTime $end) {
		return $this->provider->getHistoricStreamMeta($this, $start, $end);
	}

	public function update() {
		$data = $this->provider->fetchData($this);
		$stmt = $this->db->prepare("INSERT INTO presence_history (presence_id, datetime, type, value) VALUES (:id, :ts, :type, :val)");
		foreach ($data as $type => $val) {
			$stmt->execute(array(
				':id'		=> $this->getId(),
				':ts'		=> date('Y-m-d H:i:s'),
				':type'	=> $type,
				':val'	=> $val
			));
		}
		if (array_key_exists('popularity', $data)) {
			$stmt = $this->db->prepare("UPDATE presences SET popularity = :popularity WHERE id = :id");
			$stmt->execute(array(
				':id'				=> $this->getId(),
				':popularity'	=> $data['popularity']
			));
		}
	}

	public function saveMetric($metric, DateTime $start, DateTime $end, $value){
		$stmt = $this->db->prepare("INSERT INTO `kpi_cache` (`presence_id`, `metric`, `start_date`, `end_date`, `value`) VALUES(?,?,?,?,?)");
		$stmt->execute(array(
			$this->getId(),
			$metric,
			$start->format("Y-m-d"),
			$end->format("Y-m-d"),
			$value
		));
	}
}