<?php

class Badge_Total extends Badge_Abstract
{
	const NAME = 'total';
	
	/** @var Badge_Abstract[] */
	protected $badges = array();

	public function __construct($db, $translator, $badges)
	{
		parent::__construct($db, $translator, self::NAME);
		$this->badges = $badges;
	}

	public function calculate(Model_Presence $presence, \DateTime $date = null, Enum_Period $range = null)
	{
		if (is_null($date)) {
			$date = new \DateTime();
		}
		if (is_null($range)) {
			$range = Enum_Period::MONTH();
		}

		$total = 0;
        $count = 0;
        $scores = $presence->getBadgeScores($date, $range);
		foreach ($this->badges as $badge) {
			$badgeName = $badge->getName();
            $badgeScore = $scores[$badgeName];
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