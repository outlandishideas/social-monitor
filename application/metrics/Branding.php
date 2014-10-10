<?php

class Metric_Branding extends Metric_Abstract {

    protected static $name = "branding";
    protected static $title = "Correct Branding";

    /**
     * Returns 100 if presence has been branded correctly, else returns 0
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    protected function doCalculations(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        return $presence->getBranding();
    }

    protected function getScore(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        return $presence->getBranding() == 1 ? 100 : 0;
    }

}