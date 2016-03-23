<?php

class Metric_ActionsPerDay extends Metric_Abstract {

	const NAME = "posts_per_day";
	
    function __construct()
    {
		parent::__construct(self::NAME, "fa fa-tachometer", false);
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

        return $data['median'];
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

    /**
     * Returns an array: [min => __ , median => __ ,max => __]
     *
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return array
     */
    public function getData(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $rawData = $presence->getHistoricStreamMeta($start, $end, true);
        $min = $max = $median = 0;

        if($rawData && count($rawData)) {

            $actionsList = array_map(function ($row) {
                return $row['number_of_actions'];
            }, $rawData);

            sort($actionsList);

            if (count($actionsList)) {
                $min = $actionsList[0];
                $max = $actionsList[count($actionsList) - 1];
                $median = $this->calculate_median($actionsList);
            }
        }

        return ['min'=>$min,'median'=>$median,'max'=>$max];

    }

    function calculate_median($arr) {
        $count = count($arr); //total numbers in array
        $middleIndex = intval(floor(($count-1)/2)); // find the middle index
        if($count % 2) { // odd number, middle is the median
            $median = $arr[$middleIndex];
        } else { // even number, calculate avg of 2 medians
            $low = $arr[$middleIndex];
            $high = $arr[$middleIndex+1];
            $median = (($low+$high)/2);
        }
        return $median;
    }


}