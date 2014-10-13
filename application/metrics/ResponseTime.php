<?php

class Metric_ResponseTime extends Metric_Abstract {

    protected static $name = "response_time";
    protected static $title = "Responsiveness";
    protected static $icon = "fa fa-clock-o";

    /**
     * Counts the months between now and estimated date of reaching target audience
     * calculates score based on
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return null/string
     */
    protected function doCalculations(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $data = $presence->getResponseData($start, $end);
        if (!$data) return null;
        $total = 0;
        foreach ($data as $d) {
            $total += $d->diff;
        }
        return $total/count($data);
    }

    public function getScore(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $data = $presence->getResponseData($start, $end);
        if (!$data) return null;
        $target = BaseController::getOption('response_time_bad');

        $total = 0;
        foreach ($data as $d) {
            $total += $d->diff;
        }

        $current = $total/count($data);

        $score = round($target / $current * 100);
        $score = max(0, min(100, $score));
        return $score;
    }
}