<?php

class Metric_LikesPerView extends Metric_Abstract {

    protected static $name = "likes_per_view";
    protected static $title = "Likes per view";
    protected static $icon = "fa fa-thumbs-o-up";

    function __construct()
    {
        $this->target = floatval(BaseController::getOption('likes_per_view_best'));
    }

    /**
     * Calculates average number of likes per view
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    public function calculate(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $data = $presence->getHistoricStream($start, $end)->stream;

        $actual = null;

        if(count($data) > 0){
            $actual = 0;
            $count = 0;
            foreach ($data as $row) {
                $actual += $row['likes'];
                $count += $row['views'];
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
        if ($this->target == 0) {
            return null;
        }

        $score = $presence->getMetricValue($this);
        $score = round(100 * $score/$this->target);
        return self::boundScore($score);
    }

}