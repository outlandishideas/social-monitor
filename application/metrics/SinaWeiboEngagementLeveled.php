<?php

class Metric_SinaWeiboEngagementLeveled extends Metric_Abstract {

    protected static $name = "sina_weibo_engagement";
    protected static $title = "Sina Weibo Engagement Score";
    protected static $icon = "fa fa-weibo";
    protected static $gliding = false;

    function __construct()
    {
        $this->target = 100;
    }

    /**
     * Gets the average sina weibo engagement over the given range
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    public function calculate(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        return 100;
//        $data = $presence->getHistoricData($start, $end, self::getName());
//        $total = 0;
//        $count = 0;
//        foreach ($data as $d) {
//            $total += $d['value'];
//            $count++;
//        }
//        if ($count == 0) {
//            return 0;
//        }
//        return $total/$count;
    }

    public function getScore(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $score = $presence->getMetricValue($this);

        return $score < $this->target ? 0 : 100;
    }

}