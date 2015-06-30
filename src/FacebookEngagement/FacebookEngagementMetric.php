<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 30/04/2015
 * Time: 14:22
 */

namespace Outlandish\SocialMonitor\FacebookEngagement;


use DateTime;
use Outlandish\SocialMonitor\FacebookEngagement\Query\Query;

class FacebookEngagementMetric
{
    /**
     * @var Query
     */
    private $query;
    private $cache = [];

    public function __construct(Query $query)
    {
        $this->query = $query;
    }

    /**
     * @param int      $presenceId
     * @param DateTime $now
     * @param DateTime $then
     *
     * @return null|float
     */
    public function get($presenceId, DateTime $now, DateTime $then)
    {
        $scores = $this->getAll($now, $then);
        if (array_key_exists($presenceId, $scores)) {

            return $scores[$presenceId];
        }

        return null;
    }

    public function getMany(array $presenceIds, DateTime $now, DateTime $then)
    {
        $scores = $this->getAll($now, $then);
        return array_filter($scores, function($presenceId) use ($presenceIds) {
            return array_key_exists($presenceId, $presenceIds);
        }, ARRAY_FILTER_USE_KEY);
    }

    public function getAll(DateTime $now, DateTime $then)
    {
        $key = "{$now->format('Y-m-d')}-{$then->format('Y-m-d')}";
        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $this->query->fetch($now, $then);
        }
        return $this->cache[$key];
    }
}