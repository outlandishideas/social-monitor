<?php

class Metric_FBEngagement extends Metric_Abstract {

    protected static $name = "facebook_engagement_score";
    protected static $title = "Facebook Engagement Score";
    protected static $icon = "fa fa-facebook-square";
    protected static $gliding = false;

    function __construct()
    {
        $this->target = floatval(BaseController::getOption('fb_engagement_target'));
    }

    /**
     * Gets the average facebook engagement over the given range
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    public function calculate(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $data = $presence->getHistoricData($start, $end, self::getName());
        var_dump($data);
        $total = 0;
        $count = 0;
        foreach ($data as $d) {
            $total += $d['value'];
            $count++;
        }
        var_dump($total);
        var_dump($count);
        exit;
        if ($count == 0) {
            return 0;
        }
        return $total/$count;
    }

    public function getScore(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $score = $presence->getMetricValue($this);
        var_dump($score);
        $score = ($score < $this->target) ? 0 : 100 ;
        return $score;
    }

}