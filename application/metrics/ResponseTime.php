<?php

class Metric_ResponseTime extends Metric_Abstract {

    protected static $name = "response_time";
    protected static $title = "Responsiveness";
    protected static $icon = "fa fa-clock-o";

    function __construct()
    {
        $this->target = floatval(BaseController::getOption('response_time_bad'));
    }

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
        if (is_null($data)) return null;
        if (!$data || empty($data)) return 0;
        $total = 0;
        foreach ($data as $d) {
            $total += $d->diff;
        }
        return $total/count($data);
    }

    public function getScore(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $data = $presence->getResponseData($start, $end);
        if (is_null($data)) {
            return null;
        }
        if (empty($data)) {
            return 0;
        }

        $total = 0;
        foreach ($data as $d) {
            $total += $d->diff;
        }

        $current = $total/count($data);

        $score = round($this->target / $current * 100);
        return self::boundScore($score);
    }
}