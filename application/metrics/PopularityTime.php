<?php

class Metric_PopularityTime {

    protected $name = "popularity_time";
    protected $title = "Popularity Trend";

    /**
     * Counts the months between now and estimated date of reaching target audience
     * calculates score based on
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return null/string
     */
    protected function doCalculations(NewModel_Presence $presence, DateTime $start, DateTime $end){
        $estimate = $presence->getTargetAudienceDate($start, $end);
        $diff = $estimate->diff(new DateTime());

        $targetMonths = BaseController::getOption('achieve_audience_good');
        $actualMonths = $diff->y*12 + $diff->m;

        return min(100, $actualMonths / $targetMonths * 100);
    }

}