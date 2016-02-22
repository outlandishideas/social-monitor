<?php

use Carbon\Carbon;
use Outlandish\SocialMonitor\Engagement\Query\Query;

abstract class Metric_AbstractEngagement extends Metric_Abstract {

    protected static $name = "changethis_engagement";
    protected static $title = "ChangeThis Engagement Score";
    protected static $icon = "fa fa_change_this";
    protected static $gliding = false;
    protected static $queryClassName = 'Outlandish\SocialMonitor\Engagement\Query\ChangeThisQuery';
    /** @var Query */
    protected $query;
    public static $engagementTarget = 0.25;
    protected $cache = array();

    function __construct()
    {
        $db = Zend_Registry::get('db')->getConnection();
        $this->query = new static::$queryClassName($db);
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
        if($data and count($data)) {
            $date = null;
            $total = 0;
            $count = 0;
            foreach($data as $d) {
                $nextDate =  Carbon::parse($d['datetime'])->format('Y-m-d');
                if($date !== $nextDate) {
                    $date = $nextDate;
                    $total += $d['value'];
                    $count++;
                }
            }
            return $count > 0 ? $total / $count : 0;
        } else {
            return 0;
        }
    }

    public function getScore(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $likesPerUsers = $presence->getMetricValue($this);
        return self::convertToScore($likesPerUsers);
    }

    public static function convertToScore($raw) {
        $score = $raw / static::$engagementTarget;
        if($score > 1) {
            return 100;
        } else {
            return round($score * 100, 1);
        }
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

        return $score;
    }

}