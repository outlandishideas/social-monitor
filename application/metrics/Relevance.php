<?php

class Metric_Relevance extends Metric_Abstract {

    protected static $name = "relevance";
    protected static $title = "Relevance";
    protected static $icon = "fa fa-tags";

    /**
     * Returns score depending on number of relevant links per day against target
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    protected function doCalculations(NewModel_Presence $presence, DateTime $start, DateTime $end){
        $data = $presence->getHistoricStreamMeta($start, $end);

        $actual = null;

        if(!empty($data)){
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

            $actual = $totals['bc_links'] / count($data);
        }

        return $actual;
    }


    public function getScore(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $score = null;

        $data = $presence->getHistoricStreamMeta($start, $end);

        if (empty($data)) return null;

        $totals = array(
            'total' => 0,
            'links' => 0,
            'bc_links'  => 0
        );

        $totals = array_reduce($data, function($totals, $row) {
            $totals['total'] += $row['number_of_actions'];
            $totals['links'] += $row['number_of_links'];
            $totals['bc_links'] += $row['number_of_bc_links'];
            return $totals;
        }, $totals);

        if ($totals['total'] < BaseController::getOption('updates_per_day')) return 0;

        $targetPercent = $presence->getType()->getRelevancePercentage()/100;
        $target = $totals['total'] * $targetPercent;

        if($target > 0){
            $current = $totals['bc_links'];
            $score = round($current/$target * 100);
            $score = max(0, min(100, $score));
        }

        return $score;
    }

}