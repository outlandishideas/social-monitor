<?php

class Metric_Relevance extends Metric_Abstract {

    protected static $name = "relevance";
    protected static $title = "Relevance";
    protected static $icon = "fa fa-tags";

    protected $updatesPerDay;

    function __construct()
    {
        $this->updatesPerDay = floatval(BaseController::getOption('updates_per_day'));
    }


    /**
     * Returns score depending on number of relevant links per day against target
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    public function calculate(Model_Presence $presence, DateTime $start, DateTime $end){
        list($actual, $count) = array_values($this->getData($presence, $start, $end));

        if ($count < 1) {
            return $actual;
        }

        return $actual / $count;
    }


    public function getScore(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $score = $presence->getMetricValue($this);

        if (is_null($score)) {
            return null;
        }
        if (empty($score)) {
            return 0;
        }

        $targetPercent = $presence->getType()->getRelevancePercentage()/100;
        $numActions = $presence->getMetricValue(Metric_ActionsPerDay::getName());

        if ($numActions > 0) {
            $target = $numActions * $targetPercent;
            $score = round(100 * $score/$target);
            $score = self::boundScore($score);
        } else {
            $score = 0;
        }

        return $score;
    }

    public function getData(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $rawData = $presence->getHistoricStreamMeta($start, $end);

        $data = ['relevant_actions' => 0, 'days' => count($rawData)];

        if(!empty($rawData)){
            foreach ($rawData as $row) {
                $data['relevant_actions'] += $row['number_of_bc_links'];
            }
        }

        return $data;
    }


}