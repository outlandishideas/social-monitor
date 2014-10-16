<?php

class Badge_Total extends Badge_Abstract
{
	protected static $name = 'total';
	protected static $title = 'Overall';
	protected static $description = '<p>The overall score KPI provides an overall score for how well a social media presence, country or SBU is doing in the other three KPIs. The score combines the total score of the following three KPIs:</p>
                <ul>
                    <li>Reach KPI</li>
                    <li>Engagement KPI</li>
                    <li>Quality KPI</li>
                </ul>';

	public function calculate(NewModel_Presence $presence, \DateTime $date = null, Badge_Period $range = null)
	{
		if (is_null($date)) {
			$date = new \DateTime();
		}
		if (is_null($range)) {
			$range = Badge_Period::MONTH();
		}

		$badges = array(
			new Badge_Reach($this->db),
			new Badge_Engagement($this->db),
			new Badge_Quality($this->db)
		);

		$total = 0;
		foreach ($badges as $b) {
			$total += $b->calculate($presence, $date, $range);
		}

		$result = round($total/count($badges));
		$result = max(0, min(100, $result));

		return $result;
	}

	public function assignRanks(\DateTime $date = null, Badge_Period $range = null)
	{
		return; //do nothing
	}
}