<?php

class Metric_ResponseRatio extends Metric_Abstract {

    protected static $name = "response_ratio";
    protected static $title = "Conversation";
    protected static $icon = "fa fa-reply";

    function __construct()
    {
        $this->target = BaseController::getOption('replies_to_number_posts_best');
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
        $ret = $presence->getRatioRepliesToOthersPosts($start, $end);
        return $ret;
    }

    public function getScore(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $current = $presence->getRatioRepliesToOthersPosts($start, $end);

        $score = round($current / $this->target * 100);
        return self::boundScore($score);
    }
}