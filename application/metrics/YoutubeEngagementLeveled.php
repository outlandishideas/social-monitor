<?php

class Metric_YoutubeEngagementLeveled extends Metric_Abstract {

    protected static $name = "youtube_engagement";
    protected static $title = "Youtube Engagement Score";
    protected static $icon = "fa fa-youtube";
    protected static $gliding = false;

    function __construct()
    {
        $this->target = $this->getTargets();

        $pdo = Zend_Registry::get('db')->getConnection();
        $this->query = new \Outlandish\SocialMonitor\Engagement\Query\WeightedYoutubeEngagementQuery($pdo);
        $this->cache = [];
    }

    private function getTargets()
    {
        $target = [];
        $target[1] = floatval(BaseController::getOption('yt_engagement_target_level_1'));
        $target[2] = floatval(BaseController::getOption('yt_engagement_target_level_2'));
        $target[3] = floatval(BaseController::getOption('yt_engagement_target_level_3'));
        $target[4] = floatval(BaseController::getOption('yt_engagement_target_level_4'));
        $target[5] = floatval(BaseController::getOption('yt_engagement_target_level_5'));

        return $target;
    }

    /**
     * Gets the average Instagram engagement over the given range
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
        $score = $presence->getMetricValue($this,$start,$end);
        $level = 0;

        foreach($this->target as $level => $target) {
            //if we haven't reached level 1 yet, then
            //set the level as 0 and break out
            if ($level == 1 && $score < $target) {
                $level = 0;
                break;
            }

            //if level is more than or == to the target
            //continue to the next level
            if ($score >= $target) {
                continue;
            }

            //if level is less than or = to the target
            //break and use the current $level to calculate
            $level = $level-1;
            break;

        }

        //Each level is worth 20% so level 5 is 100%
        return $level * 20;
    }

    public function getData(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $now = clone $end;
        $then = clone $now;
        $then->modify("-1 week");

        $key = $now->format('Y-m-d') . $then->format('Y-m-d');
        if (!array_key_exists($key, $this->cache)) {
            $rows = $this->query->getData($now, $then);
            $this->cache[$key] = $rows;
        }

        $rows = $this->cache[$key];
        $presenceId = $presence->getId();

        $score = $rows[$presenceId];

        $prevMonthStart = clone $then;
        $prevMonthStart->modify("-30 days");

        $prevScore = $presence->getHistoricData($prevMonthStart,$now,self::$name);
        $min = $max = null;
        if(count($prevScore)) {
            foreach ($prevScore as $d) {
                if($min === null || $d['value'] < $min) {
                    $min = $d['value'];
                }
                if($max === null || $d['value'] > $max) {
                    $max = $d['value'];
                }
            }
        }

        $score['previous_value_min'] = $min;
        $score['previous_value_max'] = $max;

        return $score;
    }


}