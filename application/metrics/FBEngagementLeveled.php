<?php

class Metric_FBEngagementLeveled extends Metric_FBEngagement {

    protected static $name = "facebook_engagement";
    protected static $title = "Facebook Engagement Score";
    protected static $icon = "fa fa-facebook-square";
    protected static $gliding = true;

    function __construct()
    {
        $this->target = $this->getTargets();
    }

    private function getTargets()
    {
        $target = [];
        $target[1] = floatval(BaseController::getOption('fb_engagement_target_level_1'));
        $target[2] = floatval(BaseController::getOption('fb_engagement_target_level_2'));
        $target[3] = floatval(BaseController::getOption('fb_engagement_target_level_3'));
        $target[4] = floatval(BaseController::getOption('fb_engagement_target_level_4'));
        $target[5] = floatval(BaseController::getOption('fb_engagement_target_level_5'));

        return $target;
    }

    public function getScore(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $score = $presence->getMetricValue($this);

        foreach($this->target as $level => $target) {
            //if we haven't reached level 1 yet, then
            //set the level as 0 and break out
            if ($level == 1 && $score < $target) {
                $level = 0;
                break;
            }

            //if level is more than or == to the target
            //continue to the next level
            if ($level > $target) {
                continue;
            }

            //if level is less than or = to the target
            //break and use the current $level to calculate
            break;

        }

        //Each level is worth 20% so level 5 is 100%
        return $level * 20;
    }

}