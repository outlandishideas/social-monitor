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
     * Returns score depending on number of actions per day against target
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    protected function doCalculations(NewModel_Presence $presence, \DateTime $start, \DateTime $end){
        $data = $presence->getHistoricStreamMeta($start, $end);

        $actual = null;
        //if no data, do not try and calculate anything
        if(count($data) > 0){
            $actual = array_reduce($data, function($actions, $row){
                    return $actions + $row['number_of_actions'];
                }, 0) / count($data);
        }

        return $actual;

    }

    public function getScore(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        if ($this->target == 0) {
            return null;
        }
        $data = $presence->getKpiData($start, $end);
        $current = $data[self::getName()];
        $score = round($current/$this->target * 100);
        return self::boundScore($score);
    }

}