<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 01/05/2015
 * Time: 16:41
 */

namespace Outlandish\SocialMonitor\Fetcher;


use DateTime;
use Facebook\FacebookRequest;
use Facebook\FacebookRequestException;
use Facebook\FacebookSession;
use Facebook\GraphObject;

class FacebookFetcher implements Fetcher
{

    /**
     * @var FacebookSession
     */
    private $session;

    public function __construct(FacebookSession $session)
    {
        $this->session = $session;
    }

    /**
     * Fetches the posts for the $pageId and from the $since date
     *
     * @param Fetchable $fetchable
     * @param DateTime  $since
     *
     * @return mixed
     */
    public function getPosts(Fetchable $fetchable, DateTime $since)
    {
        $pageId = $fetchable->getFetchableId();
        $timestamp = $since->format('T');

        $request = new FacebookRequest(
            $this->session,
            'GET',
            "/{$pageId}/feed?since=$timestamp"
        );

        $response = $request->execute();

        /** @var GraphObject $graphObject */
        $graphObject = $response->getGraphObject();

        return $graphObject;
    }
}