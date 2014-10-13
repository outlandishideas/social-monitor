<?php

class Metric_ActionsPerDay extends Metric_Abstract {

    protected static $name = "posts_per_day";
    protected static $title = "Actions Per Day";
    protected static $icon = "fa fa-tachometer";

    /**
     * Returns score depending on number of actions per day against target
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    protected function doCalculations(NewModel_Presence $presence, \DateTime $start, \DateTime $end){
        $data = $presence->getHistoricStreamMeta($start, $end);

        $actual = 0;
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
        $data = $presence->getKpiData($start, $end);
        $current = $data[self::getName()];
        $target = BaseController::getOption('updates_per_day');
        if ($target == 0) return null;
        $score = round($current/$target * 100);
        $score = max(0, min(100, $score)); //clamp score to the 0-100 range
        return $score;
    }

}