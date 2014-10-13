<?php

class Metric_Klout extends Metric_Abstract {

    protected static $name = "klout_score";
    protected static $title = "Klout Score";
    protected static $icon = "fa fa-hand-o-right";

    /**
     * Returns 100 if presence has been signed off, else returns 0
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    protected function doCalculations(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        return $presence->getKloutScore();
    }

    public function getScore(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $target = BaseController::getOption('klout_target');
        $actual = round($presence->getKloutScore());
        $score = ($actual < $target) ? 0 : 100 ;
    }

}