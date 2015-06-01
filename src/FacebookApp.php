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
use Outlandish\SocialMonitor\FacebookFetcher\SessionFactory;
use Outlandish\SocialMonitor\FacebookFetcher\SharesCountFetcher;

class FacebookApp
{
    /**
     * @var SessionFactory
     */
    private $session;
    /**
     * @var CommentsCountFetcher
     */
    private $commentsCounter;
    /**
     * @var LikesCountFetcher
     */
    private $likesCounter;
    /**
     * @var SharesCountFetcher
     */
    private $sharesCounter;

    public function __construct(
        $session,
        LikesCountFetcher $likesCounter,
        SharesCountFetcher $sharesCounter,
        CommentsCountFetcher $commentsCounter)
    {
        $this->session = $session;
        $this->commentsCounter = $commentsCounter;
        $this->likesCounter = $likesCounter;
        $this->sharesCounter = $sharesCounter;
    }

    public function pageInfo($pageId)
    {
        return $this->getRequest("GET", "/{$pageId}")->execute()->getGraphObject();
    }

    public function pagePicture($pageId)
    {
        return $this->getRequest("GET", "/{$pageId}/picture", ['redirect' => false])->execute()->getGraphObject();
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

        $graphObject = $this->getRequest("GET", "/{$pageId}/feed", $parameters)->execute()->getGraphObject();

        return $graphObject;
    }

    public function postResponses(array $postIds)
    {
        $parameters = [
            'ids' => implode(',', $postIds)
        ];

        return $this->getRequest("GET", "/comments", $parameters)->execute()->getGraphObject();
    }

    public function get($url)
    {
        $url = str_replace('https://graph.facebook.com/v2.3', '', $url);
        return $this->getRequest("GET", $url)->execute()->getGraphObject();
    }

    public function getRequest($method, $endpoint, $parameters = [])
    {
        return new FacebookRequest($this->session->getSession(), $method, $endpoint, $parameters);
    }

    /**
     * Gets the shares for a post
     *
     * @param $postId
     * @return int
     */
    public function postShareCount($postId)
    {
        return $this->sharesCounter->getCount($postId);
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