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
use Facebook\FacebookResponse;
use Facebook\FacebookSDKException;
use Facebook\GraphObject;

/**
 * Class counts things for a given facebook object
 *
 * Class CountFetcher
 * @package Outlandish\SocialMonitor\FacebookFetcher
 */
abstract class CountFetcher
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
        $request = $this->getRequest($id);
        $response = $this->getResponse($request);
        $likes = $this->getCountFromResponse($response);

        return $likes;
    }

    /**
     * Returns the endpoint string to add to the id to generate the full endpoint
     *
     * @return string
     */
    protected abstract function getEndpoint();

    /**
     * Returns the parameters to be sent up with the request
     *
     * @return mixed
     */
    protected abstract function getParameters();

    /**
     * Constructs a request object and gets its response
     *
     * @param FacebookRequest $request
     * @return null|FacebookResponse
     */
    protected function getResponse(FacebookRequest $request)
    {
        try {
            $response = $request->execute();
        } catch (FacebookSDKException $e) {
            $response = null;
        } catch (FacebookRequestException $e) {
            $response = null;
        }

        return $response;
    }

    /**
     * Gets the likes count from the response and returns 0 if there is missing data
     *
     * @param FacebookResponse|null $response
     * @return int
     */
    protected function getCountFromResponse(FacebookResponse $response = null)
    {
        if(is_null($response)) {
            return 0;
        }

        /** @var GraphObject $graphObject */
        $graphObject = $response->getGraphObject();
        if(is_null($graphObject)) {
            return 0;
        }

        $summary = $graphObject->getProperty('summary');

        if(is_null($summary)) {
            return 0;
        }

        $count = $summary->getProperty('total_count');

        if(is_null($count)) {
            return 0;
        }

        return $count;
    }

    /**
     * @param $id
     * @return FacebookRequest
     */
    protected function getRequest($id)
    {
        $request = $this->request->getRequest(
            "GET",
            "/{$id}/{$this->getEndpoint()}",
            $this->getParameters()
        );
        return $request;
    }
}