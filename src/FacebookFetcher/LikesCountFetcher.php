<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 31/05/2015
 * Time: 16:15
 */

namespace Outlandish\SocialMonitor\FacebookFetcher;

/**
 * Class fetches likes for a given facebook object
 *
 * Class LikesCountFetcher
 * @package Outlandish\SocialMonitor\FacebookFetcher
 */
class LikesCountFetcher extends CountFetcher
{
    /**
     * Returns the endpoint string to add to the id to generate the full endpoint
     *
     * @return string
     */
    protected function getEndpoint()
    {
        return 'likes';
    }

    /**
     * Returns the parameters to be sent up with the request
     *
     * @return mixed
     */
    protected function getParameters()
    {
        return [
            'summary' => true
        ];
    }
}