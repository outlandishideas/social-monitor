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