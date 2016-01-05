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
    public function calculate(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $data = $this->getData($presence, $start, $end);

        if ($data['days'] > 0) {
            return $data['actions'];
        }

        return $data['actions'] / $data['days'];
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

    public function getData(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $rawData = $presence->getHistoricStreamMeta($start, $end, true);

        $data = ['actions' => 0, 'days' => count($rawData)];

        if(count($rawData) > 0){
            foreach ($rawData as $row) {
                $data['actions'] += $row['number_of_actions'];
            }
        }

        return $data;
    }


}