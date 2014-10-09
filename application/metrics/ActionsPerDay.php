<?php

class Metric_ActionsPerDay extends Metric_Abstract {

    protected static $name = "posts_per_day";
    protected static $title = "Actions Per Day";

    /**
     * Returns score depending on number of actions per day against target
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    protected function doCalculations(NewModel_Presence $presence, DateTime $start, DateTime $end){
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

}