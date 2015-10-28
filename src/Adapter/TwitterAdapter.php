<?php

namespace Outlandish\SocialMonitor\Adapter;

use DateTime;
use Exception_TwitterNotFound;
use Outlandish\SocialMonitor\Models\Status;
use Outlandish\SocialMonitor\Models\PresenceMetadata;
use Outlandish\SocialMonitor\Models\Tweet;
use RuntimeException;
use Util_Twitter;

class TwitterAdapter extends AbstractAdapter {

    /**
     * @param $handle
     * @return PresenceMetadata
     * @throws Exception_TwitterNotFound
     */
    public function getMetadata($handle) {

        try {
            $data = Util_Twitter::userInfo($handle);
        } catch (Exception_TwitterNotFound $e) {
            throw new Exception_TwitterNotFound('Twitter user not found: ' . $handle, $e->getCode(), $e->getPath(), $e->getErrors());
        }

        $metadata = new PresenceMetadata();
        $metadata->uid = $data->id_str;
        $metadata->image_url = $data->profile_image_url;
        $metadata->name = $data->name;
        $metadata->page_url = 'https://www.twitter.com/' . $data->screen_name;
        $metadata->popularity = $data->followers_count;

        return $metadata;

    }

    /**
     * @param $pageUID
     * @param string $handle
     * @param string $since
     * @return Status[]
     */
    public function getStatuses($pageUID,$since,$handle) {

        $tweets = Util_Twitter::userTweets($pageUID, $since);
        $mentions = Util_Twitter::userMentions($handle, $since);

        $parsedStatuses = array();

        foreach($tweets as $raw) {
            $parsedStatuses[] = $this->parseStatus($raw,false,$pageUID);
        }

        foreach($mentions as $raw) {
            $parsedStatuses[] = $this->parseStatus($raw,true,$pageUID);
        }

        return $parsedStatuses;
    }

    private function parseStatus($raw,$mention,$pageUID) {
        $parsedTweet = Util_Twitter::parseTweet($raw);
        $isRetweet = isset($raw->retweeted_status) && $raw->retweeted_status->user->id == $pageUID;
        $tweet = new Tweet();
        $tweet->id = $raw->id_str;
        $tweet->message = $parsedTweet['text_expanded'];
        $tweet->created_time = gmdate('Y-m-d H:i:s', strtotime($raw->created_at));
        $tweet->share_count = $raw->retweet_count;
        $tweet->html = $parsedTweet['html_tweet'];
        $tweet->in_response_to_user_uid = $raw->in_reply_to_user_id_str;
        $tweet->in_response_to_status_uid = $raw->in_reply_to_status_id_str;
        $tweet->isMention = $mention;
        $tweet->needs_response = $mention && !$isRetweet ? 1 : 0;
        if (!empty($raw->entities->urls) && !$mention) {
            $tweet->links = array_map(function ($a) {
                return $a->expanded_url;
            }, $raw->entities->urls);
        }
        return $parsedTweet;
    }

    /**
     * @param array $postUIDs
     * @return Status[]
     */
    public function getResponses($postUIDs) {
        throw new RuntimeException('Not implemented');
    }

}