<?php

use Outlandish\SocialMonitor\Engagement\Query\Query;

abstract class Metric_AbstractEngagement extends Metric_Abstract {

    protected static $name = "changethis_engagement";
    protected static $title = "ChangeThis Engagement Score";
    protected static $icon = "fa fa_change_this";
    protected static $gliding = false;
    protected static $targetOptions = array();
    protected static $queryClassName = 'Outlandish\SocialMonitor\Engagement\Query\ChangeThisQuery';
    /** @var Query */
    protected $query;
    public $target = array();
    protected $cache = array();

    function __construct()
    {
        $this->target = $this->getTargets();
        $db = Zend_Registry::get('db')->getConnection();
        $this->query = new static::$queryClassName($db);
    }

    protected function getTargets()
    {
        $target = [];

        for($i=0;$i<count(static::$targetOptions);$i++) {
            $target[$i+1] = floatval(BaseController::getOption(static::$targetOptions[$i]));
        }

        return $target;
    }

    /**
     * Gets the average facebook engagement over the given range
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

    public function getData(Model_Presence $presence, \DateTime $start, \DateTime $end_read_only)
    {
        $now = clone $end_read_only;
        $then = clone $now;
        $then->modify("-1 week");

        $key = $now->format('Y-m-d') . $then->format('Y-m-d');
        if (!array_key_exists($key, $this->cache)) {
            $rows = $this->query->getData($now, $then);
            $this->cache[$key] = $rows;
        }

        $rows = $this->cache[$key];
        $presenceId = $presence->getId();

        $presences = array_filter($rows, function($row) use ($presenceId) {
            return $row['presence_id'] == $presenceId;
        });

        if (empty($presences)) {
            return [];
        }

        $score = array_values($presences)[0];

        $prevMonthStart = clone $then;
        $prevMonthStart->modify("-30 days");

        $prevScore = $presence->getHistoricData($prevMonthStart,$end_read_only,static::$name);
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