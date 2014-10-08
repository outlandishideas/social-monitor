<?php

class Metric_Relevance {

    protected $name = "relevance";
    protected $title = "Relevance";

    /**
     * Returns score depending on number of relevant links per day against target
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    protected function doCalculations(NewModel_Presence $presence, DateTime $start, DateTime $end){
        $data = $presence->getHistoricStreamMeta($start, $end);

        $totals = array(
            'total' => 0,
            'links' => 0,
            'bc_links' => 0
        );

        $totals = array_reduce($data, function($totals, $row){
                $totals['total'] += $row['number_of_actions'];
                $totals['links'] += $row['number_of_links'];
                $totals['bc_links'] += $row['number_of_bc_links'];
                return $totals;
        }, $totals);

        //calculate the target
        //target is a percentage of the total actions per day
        //however target must reach a minimum, which is the percentage of the target actions per day
        //EXAMPLE:
        //Target Actions per Day = 5, Target Relevant Actions per Day = 60% (min 3)
        //1 relevant post out of 1 post on 1 day will not satisfy this metric
        //3 relevant posts out of 10 posts on 1 day will not satisfy this metric (if metric is 60%)

        $targetPercent = "0.6"; //todo: get a better way of getting options
        $target = max( $totals['total'] / count($data), BaseController::getOption('updates_per_day') ) / 100 * $targetPercent;

        $actual = $totals['bc_links'] / count($data);

        return min(100, $actual / $target * 100);

    }

}