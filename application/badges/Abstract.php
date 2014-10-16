<?php

abstract class Badge_Abstract
{
	protected static $name = '';
	protected static $title = '';
	protected $db;

	protected $metrics = array();
	protected $metricsWeighting = array();

	public function __construct(PDO $db = null)
	{
		if (is_null($db)) {
			$db = Zend_Registry::get('db')->getConnection();
		}
		$this->db = $db;
	}

	public function calculate(NewModel_Presence $presence, \DateTime $date = null, Badge_Period $range = null)
	{
		if (is_null($date)) {
			$date = new \DateTime();
		}
		if (is_null($range)) {
			$range = Badge_Period::MONTH();
		}

		$totalWeight = 0;
		$totalScore = 0;
		$start = $range->getBegin($date);
		foreach ($this->getMetricsWeighting() as $metric => $weight) {
			$score = Metric_Factory::getMetric($metric)->getScore($presence, $start, $date);
			if (is_null($score)) continue;
			$totalScore += ($score * $weight);
			$totalWeight += $weight;
		}
		$result = $totalWeight > 0 ? round($totalScore/$totalWeight) : 0; //prevent division by 0 in case of no available scores
		$result = max(0, min(100, $result));

		$presence->saveBadgeResult($result, $date, $range, static::getName());

		return $result;
	}

	public static function getName()
	{
		return static::$name;
	}

	public static function getTitle()
	{
		return static::$title;
	}

	public function getMetrics()
	{
		return $this->metrics;
	}

	public function getMetricsWeighting()
	{
		if (count($this->metricsWeighting) == 0) {
			$metrics = array();
			foreach($this->getMetrics() as $metric){
				$metrics[$metric::getName()] = 1;
			}
			foreach ($metrics as $name => $weight) {
				//get weight from database, if it exists
				$weighting = BaseController::getOption($name . '_weighting');
				if ($weighting > 0) {
					$metrics[$name] = $weighting;
				}
			}
			$this->metricsWeighting = $metrics;
		}
		return $this->metricsWeighting;
	}

	public function assignRanks(\DateTime $date = null, Badge_Period $range = null)
	{
		if (is_null($date)) {
			$date = new \DateTime();
			$date->modify('-1 day'); //badges always work on yesterday as the most recent day
		}
		if (is_null($range)) $range = Badge_Period::MONTH();

		$name = static::getName();

		$sql = "
			SELECT
				`h`.`presence_id`,
				`h`.`$name` AS `score`
			FROM
				badge_history AS h
			WHERE
				`date` = :date
				AND `daterange` = :range
		";

		$stmt = $this->db->prepare($sql);
		$stmt->execute(array(
			':date'	=> $date->format('Y-m-d'),
			':range'	=> (string) $range
		));
		$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

		//foreach row (ordered by score of the current badge type) set the ranking
		$stmt = $this->db->prepare("UPDATE `badge_history` SET `{$name}_rank` = :rank WHERE `presence_id` = :id AND `date` = :date AND `daterange` = :range");
		$data = static::doRanking($data);
		foreach($data as $i => $row) {
			$stmt->execute(array(
				':rank'		=> $row['rank'],
				':id'		=> $row['presence_id'],
				':date'		=> $date->format('Y-m-d'),
				':range'	=> (string) $range
			));
		}
	}

	/**
	 * Sort and rank the given data. Assumes a array key 'score' is present
	 * @param array $data The data to sort and rank
	 * @return array The sorted and ranked data. An array key 'rank' has been added to each row.
	 */
	public static function doRanking($data)
	{
		usort($data, function($a, $b) {
			$aVal = $a['score'];
			$bVal = $b['score'];

			if ($aVal == $bVal) return 0;

			return ($aVal > $bVal ? -1 : 1);
		});
		$lastScore = null;
		$lastRank = null;
		foreach($data as $i => &$row) {
			if ($row['score'] == $lastScore){
				$rank = $lastRank;
			} else {
				$rank = $i+1;
			}

			$row['rank'] = $rank;

			$lastScore = $row['score'];
			$lastRank = $rank;
		}
		return $data;
	}
}