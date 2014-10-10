<?php

class Metric_LikesPerPost extends Metric_Abstract {

    protected static $name = "likes_per_post";
    protected static $title = "Applause";

    /**
     * Returns score depending on number of actions per day against target
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    protected function doCalculations(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        switch ((string) $presence->getType()) {
            case NewModel_PresenceType::FACEBOOK:
                //do nothing and continue to calculations
                break;
            default:
                return null; //Likes only are available for facebook
                break;
        }
        $data = $presence->getHistoricStream($start, $end);

        $actual = 0;
        //if no data, do not try and calculate anything
        if(count($data) > 0){
            $actual = array_reduce($data, function($actions, $row){
                    return $actions + $row['likes'];
                }, 0) / count($data);
        }

        return $actual;

    }

    public function getScore(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $data = $presence->getKpiData($start, $end);
        $current = $data[self::getName()];
        $target = BaseController::getOption('likes_per_post_best');
        if ($target == 0) return null;
        $score = round($current/$target * 100);
        $score = max(0, min(100, $score)); //clamp score to the 0-100 range
        return $score;
    }

}