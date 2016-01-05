<?php

class Metric_Popularity extends Metric_Abstract {

    static protected $name = "popularity";
    static protected $title = "Popularity";
    protected static $icon = "fa fa-users";

    /**
     * Calculates the average percentage of target popularity from a range of popularity values
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    public function calculate(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $popularity = $presence->getPopularity();
        return $popularity == 0 ? null : $popularity;
    }

    public function getScore(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $current = $presence->getMetricValue($this);
        $target = $presence->getTargetAudience();

        if ($target == 0) {
            return null;
        }

        $score = round(100 * $current / $target);
        return self::boundScore($score);
    }

    public function getData(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        // TODO: Implement getData() method.
    }


}