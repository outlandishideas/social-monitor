<?php

abstract class Metric_Abstract {

    protected static $name;
    protected static $title;

    /**
     * Calculate a given metric for the passed presence within the $start and $end
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return mixed
     */
    public function calculate(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $result = static::doCalculations($presence, $start, $end);
        $presence->saveMetric(static::getName(), $start, $end, $result);
        return $result;
    }

    /**
     * run the actual calculations
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return mixed
     */
    abstract protected function doCalculations(NewModel_Presence $presence, \DateTime $start, \DateTime $end);

    /**
     * Get the metric-score for a given presence for a given daterange
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int The score between 0 and 100
     */
    abstract public function getScore(NewModel_Presence $presence, \DateTime $start, \DateTime $end);

    public static function getName()
    {
        return static::$name;
    }
    public static function getTitle()
    {
        return static::$title;
    }

}