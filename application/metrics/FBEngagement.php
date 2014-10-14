<?php

class Metric_FBEngagement extends Metric_Abstract {

    protected static $name = "facebook_engagement_score";
    protected static $title = "Facebook Engagement Score";
    protected static $icon = "fa fa-facebook-square";

    /**
     * Returns 100 if presence has been signed off, else returns 0
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    protected function doCalculations(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        return $presence->getFacebookEngagement();
    }

    public function getScore(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        if ($presence->getType() != NewModel_PresenceType::FACEBOOK) return null;

        $data = $presence->getHistoricData();
        $total = 0;
        $count = 0;
        foreach ($data as $d) {
            if ($d['type'] == 'facebook_engagement_score') {
                $total += $d['value'];
                $count++;
            }
        }
        if ($count == 0) return 0;
        $target = BaseController::getOption('fb_engagement_target');
        $actual = $total/$count;
        $score = ($actual < $target) ? 0 : 100 ;
    }

}