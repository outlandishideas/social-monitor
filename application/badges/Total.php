<?php

class Badge_Total extends Badge_Abstract
{
	protected static $name = 'total';

	public function calculate(NewModel_Presence $presence, \DateTime $date = null, Badge_Period $range = null)
	{
		if (is_null($date)) {
			$date = new \DateTime();
		}
		if (is_null($range)) {
			$range = Badge_Period::MONTH();
		}

		$badges = array(
			new Badge_Reach($presence, $data, $range),
			new Badge_Engagement($presence, $data, $range),
			new Badge_Quality($presence, $data, $range)
		);

		$total = 0;
		foreach ($badges as $b) {
			$total += $b->calculate();
		}

		$result = round($total/count($badges));
		$result = max(0, min(100, $result));

		return $result;
	}

	protected function getMetrics()
	{
		//do nothing, this badge doesn't work on metrics
	}
}