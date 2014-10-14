<?php

abstract class Badge_Abstract
{
	protected static $name = '';
	protected static $title = '';

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
		$start = $range->getStart($date);
		foreach ($this->getMetrics as $metric => $weight) {
			$score = Metric_Factory::getMetric($metric)->getScore($presence, $start, $date);
			if (is_null($score)) continue;
			$totalScore += ($score * $weight);
			$totalWeight += $weight;
		}
		$result = round($totalScore/$totalWeight);
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
		if (is_null($data)) $data = new \DateTime();
		if (is_null($range)) $range = Badge_Period::MONTH();

		$sql = "
			SELECT
				`h`.`presence_id`,
				`h`.`{static::getName()}` AS `score`
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

		usort($data, function($a, $b) {
			$aVal = $a['score'];
			$bVal = $b['score'];

			if ($aVal == $bVal) return 0;

			return ($aVal > $bVal ? -1 : 1);
		});

		//foreach row (ordered by score of the current badge type) set the ranking
		$stmt = $this->db->prepare("UPDATE `badge_history` SET `{static::getName()}_rank` = :rank WHERE `presence_id` = :id AND `date` = :date AND `daterange` = :range");
		$lastScore = null;
		$lastRank = null;
		foreach($data as $i => $row) {
			if ($row->$badgeType == $lastScore){
				$rank = $lastRank;
			} else {
				$rank = $i+1;
			}

			$stmt->execute(array(
				':rank'	=> $rank,
				':id'		=> $row['presence_id'],
				':date'	=> $date->format('Y-m-d'),
				':range'	=> (string) $range
			));

			$lastScore = $row['score'];
			$lastRank = $rank;
		}
	}
}