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
use Outlandish\SocialMonitor\FacebookFetcher\CommentsCountFetcher;
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

    public function __construct(
        RequestFactory $request,
        LikesCountFetcher $likesCounter,
        CommentsCountFetcher $commentsCounter)
    {
        $this->commentsCounter = $commentsCounter;
        $this->likesCounter = $likesCounter;
        $this->request = $request;
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
     * @return mixed
     * @throws FacebookRequestException
     */
    public function pageFeed($pageId, DateTime $since = null)
    {
        $parameters = [
            'limit' =>  250
        ];
        if ($since) {
            $parameters['since'] = $since->getTimestamp();
        }

        $graphObject = $this->request->getRequest("GET", "/{$pageId}/feed", $parameters)->execute()->getGraphObject();

        return $graphObject;
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

    /**
     * Gets the likes for a post
     *
     * @param $postId
     * @return int
     */
    public function postLikesCount($postId)
    {
        return $this->likesCounter->getCount($postId);
    }

    /**
     * Gets the comments for a post
     *
     * @param $postId
     * @return int
     */
    public function postCommentsCount($postId)
    {
        return $this->commentsCounter->getCount($postId);
    }
}