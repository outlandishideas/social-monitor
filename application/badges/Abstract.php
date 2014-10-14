<?php

abstract class Badge_Abstract
{
	protected static $name = '';
	protected static $title = '';

	protected $metrics = array();
	protected $metricsWeighting = array();

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
}