<?php

class Metric_Relevance extends Metric_Abstract {

	const NAME = "relevance";
	
    protected $updatesPerDay;

    function __construct($translator)
    {
		parent::__construct($translator, self::NAME, "fa fa-tags", false);
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
        $numActions = $presence->getMetricValue(Metric_ActionsPerDay::NAME);

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

        $data = ['actions' => 0, 'actions_with_links' => 0, 'actions_with_relevant_links' => 0, 'days' => count($rawData)];

        if(!empty($rawData)){
            foreach ($rawData as $row) {
                $data['actions'] += $row['number_of_actions'];
                $data['actions_with_links'] += $row['number_of_links'];
                $data['actions_with_relevant_links'] += $row['number_of_bc_links'];
            }
        }

        return $data;
    }


}