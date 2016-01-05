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
     *
     * todo: this currently looks at the total likes and views over all time. Change to use the video history table when there is more data
     *
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    public function calculate(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $data = $presence->getHistoryData($start, $end, ['views', 'likes']);

        $combinedData = [];

        foreach ( $data as $row ) {
            if (array_key_exists($row->type, $combinedData)) {
                $combinedData[$row->type] = [];
            }

            $combinedData[$row->type][] = $row->value;
        }

        $views = max($combinedData['views']) - min($combinedData['views']);
        $likes = max($combinedData['likes']) - min($combinedData['likes']);

        if ($views > 0) {
            $actual = $likes / $views;
        } else {
            $actual = $likes;
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