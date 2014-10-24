<?php

class Metric_Klout extends Metric_Abstract {

    protected static $name = "klout_score";
    protected static $title = "Klout Score";
    protected static $icon = "fa fa-hand-o-right";

    function __construct()
    {
        $this->target = floatval(BaseController::getOption('klout_target'));
    }

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
        $actual = round($presence->getKloutScore());
        $score = ($actual < $this->target) ? 0 : 100 ;
        return $score;
    }

}