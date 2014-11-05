<?php

class Metric_Klout extends Metric_Abstract {

    protected static $name = "klout_score";
    protected static $title = "Klout Score";
    protected static $icon = "fa fa-hand-o-right";

    function __construct()
    {
        $this->target = floatval(BaseController::getOption('klout_score_target'));
    }

    /**
     * Returns 100 if presence has been signed off, else returns 0
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    public function calculate(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        return $presence->getKloutScore();
    }

    public function getScore(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $actual = $presence->getMetricValue($this);
        $score = ($actual < $this->target) ? 0 : 100 ;
        return $score;
    }

}