<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 31/05/2015
 * Time: 16:15
 */

namespace Outlandish\SocialMonitor\FacebookFetcher;
use Facebook\GraphObject;
use Facebook\FacebookResponse;

/**
 * Class fetches likes for a given facebook object
 *
 * Class SharesCountFetcher
 * @package Outlandish\SocialMonitor\FacebookFetcher
 */
class SharesCountFetcher extends CountFetcher
{
    public function getCount($id)
    {
        $shareCount = 0;
        $request = $this->getRequest($id);

        do {
            $response = $this->getResponse($request);
            if (is_null($response)) {
                break;
            }

            $graphObject = $response->getGraphObject();
            if (is_null($graphObject)) {
                break;
            }

            $data = $graphObject->getProperty('data');
            if (is_null($data)) {
                break;
            }

            $shareCount += count($data->asArray());
        } while ($request = $response->getRequestForNextPage());

        return $shareCount;
    }


    /**
     * Returns the endpoint string to add to the id to generate the full endpoint
     *
     * @return string
     */
    protected function getEndpoint()
    {
        return 'shares';
    }

    /**
     * Returns the parameters to be sent up with the request
     *
     * @return mixed
     */
    protected function getParameters()
    {
        return [];
    }

}