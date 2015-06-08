<?php

class Metric_ResponseTimeNew extends Metric_ResponseTime {

    protected static $name = "response_time";
    protected static $title = "Responsiveness";
    protected static $icon = "fa fa-clock-o";

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
        $data = $presence->getResponseData($start, $end);
        if (is_null($data)) return null;
        if (!$data || empty($data)) return 0;

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

        return $total/$count;
    }
}