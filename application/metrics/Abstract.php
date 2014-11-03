<?php

abstract class Metric_Abstract {

    protected static $name;
    protected static $title;
    protected static $icon;

    protected $target = 0;

    /**
     * run the actual calculations
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return mixed
     */
    abstract public function calculate(NewModel_Presence $presence, \DateTime $start, \DateTime $end);

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

    public static function getIcon()
    {
        return static::$icon;
    }

    protected static function boundScore($score, $min = 0, $max = 100) {
        return max($min, min($max, $score));
    }

    public static function getInstance()
    {
        return Metric_Factory::getMetric(self::getName());
    }
}