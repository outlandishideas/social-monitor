<?php

class Metric_FBEngagement extends Metric_Abstract {

    protected static $name = "facebook_engagement";
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
        $total = 0;
        $count = 0;
        foreach ($data as $d) {
            $total += $d['value'];
            $count++;
        }
        if ($count == 0) {
            return 0;
        }
        return $total/$count;
    }

    public function getScore(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $score = $presence->getMetricValue($this);
        $score = ($score < $this->target) ? 0 : 100 ;
        return $score;
    }

    public function getData(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        // TODO: Implement getData() method.
        return [];
    }


}