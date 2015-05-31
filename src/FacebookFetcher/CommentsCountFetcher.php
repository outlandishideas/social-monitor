<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 31/05/2015
 * Time: 16:15
 */

namespace Outlandish\SocialMonitor\FacebookFetcher;


use Facebook\FacebookRequest;
use Facebook\FacebookRequestException;
use Facebook\FacebookSDKException;
use Facebook\GraphObject;

/**
 * Class fetches comment counts for a given facebook object
 *
 * Class CommentsCountFetcher
 * @package Outlandish\SocialMonitor\FacebookFetcher
 */
class CommentsCountFetcher extends CountFetcher
{

    /**
     * Returns the endpoint string to add to the id to generate the full endpoint
     *
     * @return string
     */
    protected function getEndpoint()
    {
        return "comments";
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