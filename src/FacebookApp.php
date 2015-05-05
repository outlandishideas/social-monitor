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

class FacebookApp
{
    private $id;
    private $secret;
    /**
     * @var FacebookSession
     */
    private $session;

    public function __construct($id, $secret)
    {
        $this->id = $id;
        $this->secret = $secret;
    }

    public function pageInfo($pageId)
    {
        return $this->getRequest("GET", "/{$pageId}")->execute()->getGraphObject();
    }

    public function pagePicture($pageId)
    {
        return $this->getRequest("GET", "/{$pageId}/picture")->execute()->getGraphObject();
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
        $parameters = [];
        if ($since) {
            $parameters['since'] = $since->getTimestamp();
        }

        $graphObject = $this->getRequest("GET", "/{$pageId}/feed", $parameters)->execute()->getGraphObject();

        return $graphObject;
    }

    public function pagePosts(array $postIds)
    {
        $parameters = [
            'ids' => implode(',', $postIds)
        ];

        return $this->getRequest("GET", "/", $parameters)->execute()->getGraphObject();
    }

    public function get($url)
    {
        $url = str_replace('https://graph.facebook.com/v2.3', '', $url);
        return $this->getRequest("GET", $url)->execute()->getGraphObject();
    }

    public function getRequest($method, $endpoint, $parameters = [])
    {
        return new FacebookRequest($this->getSession(), $method, $endpoint, $parameters);
    }

    private function getSession()
    {
        if ($this->session === null) {
            FacebookSession::setDefaultApplication($this->id, $this->secret);

            $this->session = new FacebookSession($this->getAccessToken());
        }

        return $this->session;
    }

    /**
     * Gets the shares for a post
     *
     * @param $postId
     * @return mixed
     * @throws FacebookRequestException
     */
    public function postShares($postId)
    {
        return $this->getRequest("GET", "/{$postId}/sharedposts")->execute()->getGraphObject();
    }

    /**
     * Gets the likes for a post
     *
     * @param $postId
     * @return mixed
     * @throws FacebookRequestException
     */
    public function postLikes($postId)
    {
        $parameters = [
            'summary' => true
        ];
        return $this->getRequest("GET", "/{$postId}/likes", $parameters)->execute()->getGraphObject();
    }

    /**
     * Gets the comments for a post
     *
     * @param $postId
     * @return mixed
     * @throws FacebookRequestException
     */
    public function postComments($postId)
    {
        $parameters = [
            'summary' => true
        ];
        return $this->getRequest("GET", "/{$postId}/comments", $parameters)->execute()->getGraphObject();
    }

    private function getAccessToken()
    {
        return "{$this->id}|{$this->secret}";
    }

}