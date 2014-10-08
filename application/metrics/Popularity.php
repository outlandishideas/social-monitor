<?php

class Metric_Popularity {

    protected $name = "popularity";
    protected $title = "Popularity";

    /**
     * Calculates the average percentage of target popularity from a range of popularity values
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    protected function doCalculations(NewModel_Presence $presence, DateTime $start, DateTime $end){
        $target = $presence->getTargetAudience();
        $popularity = $presence->getPopularity();

        //if target is null, then we haven't got a good target and should return null
        return $target ? min(100, 100 * $popularity / $target) : null;
    }

}