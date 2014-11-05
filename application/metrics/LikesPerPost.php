<?php

class Metric_LikesPerPost extends Metric_Abstract {

    protected static $name = "likes_per_post";
    protected static $title = "Applause";
    protected static $icon = "fa fa-thumbs-o-up";

    function __construct()
    {
        $this->target = floatval(BaseController::getOption('likes_per_post_best'));
    }

    /**
     * Calculates average number of likes per post
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    public function calculate(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        if (!$presence->isForFacebook()) {
            return null;
        }
        $data = $presence->getHistoricStream($start, $end)->stream;

        $actual = null;

        if(count($data) > 0){
            $actual = 0;
            $count = 0;
            foreach ($data as $row) {
                if ($row['posted_by_owner']) {
                    $actual += $row['likes'];
                    $count++;
                }
            }
            $actual /= $count;
        }

        return $actual;

    }

    public function getScore(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
    {
        if ($this->target == 0 || !$presence->isForFacebook()) {
            return null;
        }

        $score = $presence->getMetricValue($this);
        $score = round(100 * $score/$this->target);
        return self::boundScore($score);
    }

}