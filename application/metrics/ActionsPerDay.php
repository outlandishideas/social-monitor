<?php

class Metric_ActionsPerDay {

    protected $name = "posts_per_day";
    protected $title = "Actions Per Day";

    /**
     * Returns score depending on number of actions per day against target
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    protected function doCalculations(NewModel_Presence $presence, DateTime $start, DateTime $end){
        $data = $presence->getHistoricStreamMeta($start, $end);

        $target = BaseController::getOption('updates_per_day');
        $actual = array_reduce($data, function($actions, $row){
            return $actions + $row['number_of_actions'];
        }, 0) / count($data);

        return min(100, $actual / $target * 100);

    }

}