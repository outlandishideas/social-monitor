<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 30/04/2015
 * Time: 18:06
 */

namespace Outlandish\SocialMonitor\FacebookEngagement;


class FacebookEngagementMetricFactory
{
    private function getQueries()
    {
        return [
            'standard' => 'Outlandish\SocialMonitor\FacebookEngagement\Query\StandardFacebookEngagementQuery',
            'weighted' => 'Outlandish\SocialMonitor\FacebookEngagement\Query\WeightedFacebookEngagementQuery'
        ];
    }

    private function getQuery($type)
    {
        $queries = $this->getQueries();
        if (array_key_exists($type, $queries)) {

            return new $queries[$type](\Zend_Registry::get('db')->getConnection());
        }

        return null;
    }

    public function getMetric($type)
    {
        $query = $this->getQuery($type);
        if (!$query) {
            return null;
        }

        return new FacebookEngagementMetric($query);
    }
}