<?php

class Metric_PopularityTime extends Metric_Abstract {

    protected static $name = "popularity_time";
    protected static $title = "Popularity Trend";
    protected static $icon = "fa fa-line-chart";

    function __construct()
    {
        $this->target = floatval(BaseController::getOption('achieve_audience_good'));
    }

    /**
     * Counts the months between now and estimated date of reaching target audience
     * calculates score based on
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return null/string
     */
    protected function doCalculations(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $estimate = $presence->getTargetAudienceDate($start, $end);
        $actualMonths = null;
        if ($estimate instanceof \DateTime) {
            $now = new DateTime('now');
            $now->setTime(0,0,0);
            $diff = $estimate->diff($now);
            $actualMonths = $diff->y*12 + $diff->m;
        }
        return $actualMonths;
    }

    public function getScore(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $score = null;

        if ($this->target > 0) {
            $data = $presence->getKpiData($start, $end);
            $current = $data[self::getName()];

            if($current > 0){
                $score = round($this->target / $current * 100);
                $score = self::boundScore($score);
            } else if ($current === 0 || $current === '0') {
                // target is already reached
                $score = 100;
            }
        }

        return $score;
    }
}