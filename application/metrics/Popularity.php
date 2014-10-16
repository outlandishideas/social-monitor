<?php

class Metric_Popularity extends Metric_Abstract {

    static protected $name = "popularity";
    static protected $title = "Popularity";
    protected static $icon = "fa fa-users";

    /**
     * Calculates the average percentage of target popularity from a range of popularity values
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    protected function doCalculations(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $popularity = $presence->getPopularity();
        return $popularity == 0 ? null : $popularity;
    }

    public function getScore(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $current = $presence->getPopularity();
        $target = $presence->getTargetAudience();

        if ($target == 0) return null;

        $score = round($current / $target * 100);
        $score = max(0, min(100, $score));
        return $score;
    }
}