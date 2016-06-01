<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 01/06/2015
 * Time: 12:40
 */

namespace Outlandish\SocialMonitor\FacebookFetcher;
use DateTime;
use Facebook\GraphObject;

/**
 * Class that fetches the feed of a page and returns it as an array of associative arrays
 *
 * Class FeedFetcher
 * @package Outlandish\SocialMonitor\FacebookFetcher
 */
class FeedFetcher {

    /**
     * @var RequestFactory
     */
    private $request;
    /**
     * @var PostParser
     */
    private $parser;

    public function __construct(RequestFactory $request, PostParser $parser)
    {
        $this->request = $request;
        $this->parser = $parser;
    }

    /**
     * @param string   $id
     * @param DateTime $since
     *
     * @return array
     */
    public function getFeed($id, DateTime $since = null)
    {
        $posts = [];

        $parameters = [
            'limit' =>  100,
            'fields' => 'message,created_time,from,shares,link'
        ];
        if(!is_null($since)) {
            $parameters['since'] = $since->getTimestamp();
        }

        $request = $this->request->getRequest("GET", "/{$id}/feed", $parameters);

        $response = $request->execute();
        if(is_null($response)) {
            return $posts;
        }

        /** @var GraphObject $graphObject */
        $graphObject = $response->getGraphObject();
        if(is_null($graphObject)) {
            return $posts;
        }

        $postArray = $graphObject->getPropertyAsArray('data');
        /** @var GraphObject $post */
        foreach($postArray as $post) {
            $posts[] = $this->handlePost($post);
        }

        return $posts;
    }

    /**
     * @param GraphObject $post
     * @return array
     */
    private function handlePost(GraphObject $post)
    {
        return $this->parser->parse($post);
    }

}