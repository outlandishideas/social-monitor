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

class LikesFetcher
{
    /**
     * @var RequestFactory
     */
    private $request;

    public function __construct(RequestFactory $request)
    {
        $this->request = $request;
    }

    /**
     * Gets a count of all the likes for a facebook object (eg. post)
     *
     * @param $id
     *
     * @return int
     * @throws \Facebook\FacebookRequestException
     */
    public function getCount($id)
    {
        $parameters = [
            'summary' => true
        ];
        $endpoint = "/{$id}/likes";

        $request = $this->request->getRequest(
            "GET",
            $endpoint,
            $parameters
        );

        try {
            $response = $request->execute();
        } catch (FacebookSDKException $e) {
            return 0;
        } catch (FacebookRequestException $e) {
            return 0;
        }

        $likes = $this->getLikesCountFromResponse($response);

        return $likes;
    }

    private function getLikesCountFromResponse($response)
    {
        /** @var GraphObject $graphObject */
        $graphObject = $response->getGraphObject();

        if(is_null($graphObject)) {
            return 0;
        }

        $summary = $graphObject->getProperty('summary');

        if(is_null($summary)) {
            return 0;
        }

        $likes = $summary->getProperty('total_count');

        if(is_null($likes)) {
            return 0;
        }

        return $likes;
    }
}