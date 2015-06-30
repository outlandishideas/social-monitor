<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 04/05/2015
 * Time: 13:22
 */

namespace Outlandish\SocialMonitor;


use DateTime;
use Facebook\FacebookRequest;
use Facebook\FacebookRequestException;
use Facebook\FacebookSession;
use Outlandish\SocialMonitor\FacebookEngagement\FacebookEngagementMetric;
use Outlandish\SocialMonitor\FacebookFetcher\CommentsCountFetcher;
use Outlandish\SocialMonitor\FacebookFetcher\FeedFetcher;
use Outlandish\SocialMonitor\FacebookFetcher\LikesCountFetcher;
use Outlandish\SocialMonitor\FacebookFetcher\RequestFactory;
use Outlandish\SocialMonitor\FacebookFetcher\SessionFactory;
use Outlandish\SocialMonitor\FacebookFetcher\SharesCountFetcher;

class FacebookApp
{
    /**
     * @var CommentsCountFetcher
     */
    private $commentsCounter;
    /**
     * @var LikesCountFetcher
     */
    private $likesCounter;
    /**
     * @var RequestFactory
     */
    private $request;
    /**
     * @var FeedFetcher
     */
    private $feed;
    /**
     * @var FacebookEngagementMetric
     */
    private $engagementMetric;

    /**
     * @return FacebookEngagementMetric
     */
    public function getEngagementMetric()
    {
        return $this->engagementMetric;
    }

    public function __construct(
        RequestFactory $request,
        LikesCountFetcher $likesCounter,
        CommentsCountFetcher $commentsCounter,
        FeedFetcher $feed,
        FacebookEngagementMetric $engagementMetric)
    {
        $this->commentsCounter = $commentsCounter;
        $this->request = $request;
        $this->feed = $feed;
        $this->likesCounter = $likesCounter;
        $this->engagementMetric = $engagementMetric;
    }

    public function pageInfo($pageId)
    {
        return $this->request->getRequest("GET", "/{$pageId}")->execute()->getGraphObject();
    }

    public function pagePicture($pageId)
    {
        return $this->request->getRequest("GET", "/{$pageId}/picture", ['redirect' => false])->execute()->getGraphObject();
    }

    /**
     * Gets the page feed for the page
     *
     * @param $pageId
     * @param DateTime $since
     * @return array
     */
    public function pageFeed($pageId, DateTime $since = null)
    {
        return $this->feed->getFeed($pageId, $since);
    }

    public function postResponses(array $postIds)
    {
        $parameters = [
            'ids' => implode(',', $postIds)
        ];

        return $this->request->getRequest("GET", "/comments", $parameters)->execute()->getGraphObject();
    }

    public function get($url)
    {
        $url = str_replace('https://graph.facebook.com/v2.3', '', $url);
        return $this->request->getRequest("GET", $url)->execute()->getGraphObject();
    }

}