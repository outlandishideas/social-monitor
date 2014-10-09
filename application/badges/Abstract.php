<?php

abstract class Badge_Abstract
{
	protected static $name = '';

	protected $metrics = array();

	public function calculate(NewModel_Presence $presence, \DateTime $date = null, Badge_Period $range = null)
	{
		if (is_null($date)) {
			$date = new \DateTime();
		}
		if (is_null($range)) {
			$range = Badge_Period::MONTH();
		}
		$start = $range->getBegin($date);
		$data = $presence->getKpiData($start, $date);
		$result = $this->doCalculation($data);

		$presence->saveBadgeResult($result, $date, $range, static::getName());

		return $result;
	}

	public static function getName()
	{
		return $this->name;
	}

	abstract protected function doCalculation($data);

	abstract protected function getMetrics();
}