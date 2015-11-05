<?php

class Metric_LikesPerPost extends Metric_Abstract {

    protected static $name = "likes_per_post";
    protected static $title = "Likes per post";
    protected static $icon = "fa fa-thumbs-o-up";

    function __construct()
    {
        $this->target = floatval(BaseController::getOption('likes_per_post_best'));
    }

    /**
     * Calculates average number of likes per post
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    public function calculate(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        if (!$presence->isForFacebook() && !$presence->isForInstagram()) {
            return null;
        }
        $data = $presence->getHistoricStream($start, $end)->stream;

        $actual = null;

        if(count($data) > 0){
            $actual = 0;
            $count = 0;
            foreach ($data as $row) {
                //instagram => posted_by_owner can only be true
                if ($presence->isForInstagram() || $row['posted_by_owner']) {
                    $actual += $row['likes'];
                    $count++;
                }
            }
            if ($count == 0) {
                $actual = 0;
            } else {
                $actual /= $count;
            }
        }

        return $actual;

    }

    public function getScore(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        if ($this->target == 0 || (!$presence->isForFacebook() && !$presence->isForInstagram())) {
            return null;
        }

        $score = $presence->getMetricValue($this);
        $score = round(100 * $score/$this->target);
        return self::boundScore($score);
    }

}