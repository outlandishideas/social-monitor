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

	public function calculate(Model_Presence $presence, \DateTime $date = null, Enum_Period $range = null)
	{
		if (is_null($date)) {
			$date = new \DateTime();
		}
		if (is_null($range)) {
			$range = Enum_Period::MONTH();
		}

		$badgeNames = array(
			Badge_Reach::getName(),
            Badge_Engagement::getName(),
            Badge_Quality::getName()
		);

		$total = 0;
        $count = 0;
        $scores = $presence->getBadgeScores($date, $range);
		foreach ($badgeNames as $b) {
            $badgeScore = $scores[$b];
            if (!is_null($badgeScore)) {
    			$total += $badgeScore;
                $count++;
            }
		}

        if ($count == 0) {
            return null;
        }

		$result = round($total/$count);
		$result = max(0, min(100, $result));

		return $result;
	}

	public function assignRanks(\DateTime $date = null, Enum_Period $range = null)
	{
		return; //do nothing
	}
}