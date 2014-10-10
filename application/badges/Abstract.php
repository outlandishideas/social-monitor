<?php

abstract class Badge_Abstract
{
	protected static $name = '';
	protected static $title = '';

	protected $metrics = array();

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
			$totalScore += (Metric_Factory::getMetric($metric)->getScore($presence, $start, $date) * $weight);
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

	abstract protected function getMetrics();
}