<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 01/06/2015
 * Time: 13:15
 */

namespace Outlandish\SocialMonitor\FacebookFetcher;

use DateTime;
use Facebook\GraphObject;

/**
 * Parses a Graph Object into a flat associative array of values
 *
 * Class PostParser
 * @package Outlandish\SocialMonitor\FacebookFetcher
 */
class PostParser {

    /**
     * @var CommentsCountFetcher
     */
    private $commentsCounter;
    /**
     * @var LikesCountFetcher
     */
    private $likesCounter;

    public function __construct(CommentsCountFetcher $commentsCounter, LikesCountFetcher $likesCounter)
    {
        $this->commentsCounter = $commentsCounter;
        $this->likesCounter = $likesCounter;
    }

    public function parse(GraphObject $post)
    {
        $postArray = $post->asArray();
        $actorId = $postArray['from']->id;
        $createdTime = date_create_from_format(DateTime::ISO8601, $postArray['created_time']);
        return array(
            'post_id' => $postArray['id'],
            'message' => isset($postArray['message']) ? $postArray['message'] : null,
            'created_time' => gmdate("Y-m-d H:i:s", $createdTime->getTimestamp()),
            'actor_id' => $actorId,
            'likes' => $this->getLikesCount($post),
            'comments' => $this->getCommentsCount($post),
            'share_count' => $this->getShareCount($post),
            'permalink' => isset($postArray['link']) ? $postArray['link'] : null,
            'type' => null,
            'in_response_to' => null
        );
    }

    /**
     * Gets the comments for a post
     *
     * @param GraphObject $post
     * @return int
     */
    public function getCommentsCount(GraphObject $post)
    {
        return $this->commentsCounter->getCount($post->getProperty('id'));
    }

    /**
     * Get the likes count for the post
     *
     * @param GraphObject $post
     * @return int
     */
    public function getLikesCount(GraphObject $post)
    {
        return $this->likesCounter->getCount($post->getProperty('id'));
    }

    /**
     * Gets the share count for the given post
     *
     * @param GraphObject $post
     * @return int
     */
    private function getShareCount($post)
    {
        $shares = $post->getProperty('shares');
        if (is_null($shares)) {
            return 0;
        }

        $count = $shares->getProperty('count');
        if (is_null($count)) {
            return 0;
        }

        return $count;
    }
}