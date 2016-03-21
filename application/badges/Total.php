<?php

class Badge_Total extends Badge_Abstract
{
	protected static $instance;

	public function calculate(Model_Presence $presence, \DateTime $date = null, Enum_Period $range = null)
	{
		if (is_null($date)) {
			$date = new \DateTime();
		}
		if (is_null($range)) {
			$range = Enum_Period::MONTH();
		}

		$badgeNames = array(
			Badge_Reach::getInstance()->getName(),
            Badge_Engagement::getInstance()->getName(),
            Badge_Quality::getInstance()->getName()
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