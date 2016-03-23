<?php

class Metric_ResponseTime extends Metric_Abstract {

    protected static $name = "response_time";
    protected static $title = "Responsiveness";
    protected static $icon = "fa fa-clock-o";

    function __construct()
    {
        $this->target = BaseController::getOption('response_time_bad');
    }

	/**
	 * Counts the months between now and estimated date of reaching target audience
	 * calculates score based on
	 * @param Model_Presence $presence
	 * @param DateTime $start
	 * @param DateTime $end
	 * @return null/string
	 */
	public function calculate(Model_Presence $presence, \DateTime $start, \DateTime $end)
	{
		return $this->getData($presence,$start,$end)['average_response_time'];
	}

	public function getData(Model_Presence $presence, \DateTime $start, \DateTime $end) {
		$data = $presence->getResponseData($start, $end);
		if (is_null($data)) return ['average_response_time' => null];
		if (!$data || empty($data)) return ['average_response_time' => 0];

		$diffs = array_map(function($row) {
			return $row->diff;
		}, $data);

		$total = array_sum($diffs);
		$count = count($diffs);

		//if we have less than 5 values, don't remove min and max values
		//else get min and max values and remove from the total
		if (count($diffs) >= 5) {
			$total -= min($diffs);
			$total -= max($diffs);
			//remove two from the count to compensate for removing the max and min values
			$count -= 2;
		}

		return ['average_response_time' => $total/$count];
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

        if ($score > BaseController::getOption('response_time_bad')) {
            return 0;
        }

        $score = 100 - round(100 * $score / $this->target);
        return self::boundScore($score);
    }

}