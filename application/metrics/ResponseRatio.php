<?php

class Metric_ResponseRatio extends Metric_Abstract {

    protected static $name = "response_ratio";
    protected static $title = "Conversation";
    protected static $icon = "fa fa-reply";

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
        return $ret == 0 ? null : $ret;
    }

    public function getScore(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $target = BaseController::getOption('replies_to_number_posts_best');
        $current = $presence->getRatioRepliesToOthersPosts($start, $end);

        $score = round($current / $target * 100);
        $score = max(0, min(100, $score));
        return $score;
    }
}