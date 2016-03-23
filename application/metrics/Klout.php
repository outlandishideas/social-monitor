<?php

class Metric_Klout extends Metric_Abstract {

    function __construct()
    {
		parent::__construct("klout_score", "fa fa-hand-o-right", false);
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
        return $actual;
    }

    public function getData(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        // TODO: Implement getData() method.
        return [];
    }


}