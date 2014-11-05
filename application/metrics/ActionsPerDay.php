<?php

class Metric_ActionsPerDay extends Metric_Abstract {

    protected static $name = "posts_per_day";
    protected static $title = "Actions Per Day";
    protected static $icon = "fa fa-tachometer";

    function __construct()
    {
        $this->target = floatval(BaseController::getOption('updates_per_day'));
    }

    /**
     * Returns average number of actions per day
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    public function calculate(Model_Presence $presence, \DateTime $start, \DateTime $end){
        $data = $presence->getHistoricStreamMeta($start, $end);

        $actual = null;

        if(count($data) > 0){
            $actual = 0;
            foreach ($data as $row) {
                $actual += $row['number_of_actions'];
            }
            $actual /= count($data);
        }

        return $actual;

    }

    public function getScore(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        if ($this->target == 0) {
            return null;
        }
        $score = $presence->getMetricValue($this);
        $score = round(100 * $score/$this->target);
        return self::boundScore($score);
    }

}