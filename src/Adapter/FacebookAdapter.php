<?php

namespace Outlandish\SocialMonitor\Adapter;

use DateTime;
use Facebook\GraphObject;
use Outlandish\SocialMonitor\FacebookApp;
use Outlandish\SocialMonitor\Models\FacebookStatus;
use Outlandish\SocialMonitor\Models\Status;
use Outlandish\SocialMonitor\Models\PresenceMetadata;
use Facebook\FacebookRequestException;
use Exception_FacebookNotFound;
use RuntimeException;

class FacebookAdapter extends AbstractAdapter {

    public function __construct(FacebookApp $facebook) {
        $this->facebook = $facebook;
    }

    /**
     * @param $handle
     * @return PresenceMetadata
     * @throws Exception_FacebookNotFound
     */
    public function getMetadata($handle) {

        try {
            $data = $this->facebook->pageInfo($handle);
        } catch (FacebookRequestException $e) {
            throw new Exception_FacebookNotFound('Facebook page not found: ' . $handle, $e->getCode(), [], []);
        }
        $metadata = new PresenceMetadata();
        $metadata->uid = $data->getProperty('id');
        $metadata->name = $data->getProperty('name');
        $metadata->page_url = $data->getProperty('link');
        $metadata->popularity = $data->getProperty('likes');
        $metadata->image_url = $this->getPicture($handle);

        return $metadata;

    }

    private function getPicture($handle)
    {
        try {
            $data = $this->facebook->pagePicture($handle);
        } catch (FacebookRequestException $e) {
            return null;
        }

        return $data->getProperty('url');

    }

    /**
     * @param $pageUID
     * @param $handle - not used, Facebook doesn't let you search posts
     * @param DateTime $since
     * @return Status[]
     */
    public function getStatuses($pageUID,$since,$handle = null) {

        $rawStatuses = $this->facebook->pageFeed($pageUID,$since);

        $parsedStatuses = array();

        foreach($rawStatuses as $raw) {
            $postedByOwner = ($raw['actor_id'] == $pageUID);
            $parsed = new FacebookStatus();
            $parsed->id = $raw['post_id'];
            $parsed->message = $raw['message'];
            $parsed->created_time = $raw['created_time'];
            $parsed->actor_id = $raw['actor_id'];
            $parsed->comments = $raw['comments'];
            $parsed->likes = $raw['likes'];
            $parsed->share_count = $raw['share_count'];
            $parsed->permalink = $raw['permalink'];
            $parsed->type = $raw['type'];
            $parsed->posted_by_owner = $postedByOwner;
            $parsed->needs_response = !$postedByOwner && $raw['message'];
            $parsedStatuses[] = $parsed;
        }

        return $parsedStatuses;
    }

    /**
     * @param array $postUIDs
     * @return Status[]
     */
    public function getResponses($postUIDs) {
        $parsedResponses = array();

        $responses = $this->facebook->postResponses($postUIDs);

        foreach($postUIDs as $postId) {
            $comments = $responses->getProperty($postId)->getPropertyAsArray('data');

            /** @var GraphObject $post */
            foreach ($comments as $post) {
                $parsed = new FacebookStatus();
                $postArray = $post->asArray();
                $actorId = $postArray['from']->id;
                $createdTime = date_create_from_format(DateTime::ISO8601, $postArray['created_time']);

                $parsed->id = $postArray['id'];
                $parsed->actor_id = $actorId;
                $parsed->message = isset($postArray['message']) ? $postArray['message'] : null;
                $parsed->created_time = gmdate("Y-m-d H:i:s", $createdTime->getTimestamp());
                $parsed->posted_by_owner = true;
                $parsed->in_response_to_status_uid = $postArray['to']->data[0]->id;
                if($parsed->posted_by_owner && $parsed->message) {
                    $parsed->links = $this->extractLinks($parsed->message);
                }

                $parsedResponses[] = $parsed;
            }
        }

        return $parsedResponses;
    }

    private function extractLinks($message) {
        $links = array();
        if (preg_match_all('/[^\s]{5,}/', $message, $tokens)) {
            foreach ($tokens[0] as $token) {
                $token = trim($token, '.,;!"()');
                if (filter_var($token, FILTER_VALIDATE_URL)) {
                    try {
                        $links[] = $token;
                    } catch (RuntimeException $ex) {
                        // ignore failed URLs
                        $failedLinks[] = $token;
                    }
                }
            }
        }
        return $links;
    }

}