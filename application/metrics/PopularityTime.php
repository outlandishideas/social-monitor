<?php

class Metric_PopularityTime extends Metric_Abstract {

    protected static $name = "popularity_time";
    protected static $title = "Popularity Trend";

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
        $actualMonths = 0;
        if($estimate){
            $diff = $estimate->diff(new DateTime());
            $actualMonths = $diff->y*12 + $diff->m;
        }
        return $actualMonths;
    }

    public function getScore(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $data = $presence->getKpiData($start, $end);
        $current = $data[self::getName()];
        $target = BaseController::getOption('achieve_audience_good');
        if ($target == 0) return null;

        $score = round($target / $current * 100);
        $score = max(0, min(100, $score));
        return $score;
    }
}