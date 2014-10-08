<?php

abstract class Metric_Abstract {

    protected $name;
    protected $title;

    /**
     * Calculate a given metric for the passed presence within the $start and $end
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return mixed
     */
    public function calculate(NewModel_Presence $presence, DateTime $start, DateTime $end)
    {
        $result = self::doCalculations($presence, $start, $end);
        $presence->saveMetric($this->getName(), $start, $end, $result);
        return $result;
    }

    /**
     * run the actual calculations
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return mixed
     */
    abstract protected function doCalculations(NewModel_Presence $presence, DateTime $start, DateTime $end);

    public function getName()
    {
        return $this->name;
    }
    public function getTitle()
    {
        return $this->name;
    }

}