<?php

class Metric_SignOff extends Metric_Abstract {

    protected static $name = "sign_off";
    protected static $title = "Signed Off";

    /**
     * Returns 100 if presence has been signed off, else returns 0
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    protected function doCalculations(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        return $presence->getSignOff();
    }

    protected function getScore(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        return $presence->getSignOff() == 1 ? 100 : 0;
    }

}