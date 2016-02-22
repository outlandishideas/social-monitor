<?php

namespace Outlandish\SocialMonitor\Adapter;

use DateTime;
use Exception_InstagramNotFound;
use Google_Service_YouTube_Channel;
use Google_Service_YouTube_ChannelSnippet;
use Google_Service_YouTube_ChannelStatistics;
use Google_Service_YouTube_Comment;
use Google_Service_YouTube_CommentSnippet;
use Google_Service_YouTube_CommentThread;
use Google_Service_YouTube_CommentThreadListResponse;
use Google_Service_YouTube_CommentThreadReplies;
use Google_Service_YouTube_CommentThreadSnippet;
use Google_Service_YouTube_ThumbnailDetails;
use Outlandish\SocialMonitor\InstagramApp;
use Outlandish\SocialMonitor\Models\InstagramStatus;
use Outlandish\SocialMonitor\Models\PresenceMetadata;
use Outlandish\SocialMonitor\Models\Status;
use Outlandish\SocialMonitor\Models\YoutubeComment;
use Outlandish\SocialMonitor\Models\YoutubeVideo;

class YoutubeAdapter extends AbstractAdapter
{

    private $channels = [];

    public function __construct(\Google_Service_YouTube $youtube)
    {
        $this->youtube = $youtube;
    }

    public function getMetadata($handle)
    {
        /** @var Google_Service_Youtube_Channel $channel */
        $channel = $this->getChannel($handle);
        /** @var Google_Service_Youtube_ChannelSnippet $snippet */
        $snippet = $channel->getSnippet();
        /** @var Google_Service_Youtube_ThumbnailDetails $thumbnails */
        $thumbnails = $snippet->getThumbnails();

        /** @var Google_Service_Youtube_ChannelStatistics $statistics */
        $statistics = $channel->getStatistics();

        $metadata = new PresenceMetadata();
        $metadata->uid = $channel->id;
        $metadata->image_url = $thumbnails->getDefault()->url;
        $metadata->page_url = 'https://www.youtube.com/user/' . $handle;
        $metadata->name = $snippet->getTitle();
        $metadata->popularity = $statistics->getSubscriberCount(); //

        return $metadata;
    }

    /**
     * Get the channel from the api, or from the $channels property if already fetched.
     * $handle might be a name, e.g. 'britishcouncil', or an id, e.g. 'UCRyQuDpPzXIHxB4BTM3uKiQ'.
     * We first assume its an id, then assume its a name.
     *
     * @param string $handle
     * @return Google_Service_YouTube_Channel
     * @throws \Exception
     */
    private function getChannel($handle)
    {
        $channel = $this->getChannelById($handle);
        if(!$channel) {
            $channel = $this->getChannelByName($handle);
            if(!$channel) {
                throw new \Exception("Youtube Channel \"{$handle}\" not found.");
            }
        }

        return $channel;
    }

    private function getChannelById($handle) {
        if (!array_key_exists($handle, $this->channels)) {
            $response = $this->youtube->channels->listChannels('id,snippet,statistics', ['id' => $handle]);

            /** @var Google_Service_Youtube_Channel[] $items */
            $items = $response->getItems();

            if (empty($items)) {
                return null;
            }

            $this->channels[$handle] = $items[0];
        }

        return $this->channels[$handle];
    }

    private function getChannelByName($handle) {
        if (!array_key_exists($handle, $this->channels)) {
            $response = $this->youtube->channels->listChannels('id,snippet,statistics', ['forUsername' => $handle]);

            /** @var Google_Service_Youtube_Channel[] $items */
            $items = $response->getItems();

            if (empty($items)) {
                return null;
            }

            $this->channels[$handle] = $items[0];
        }

        return $this->channels[$handle];
    }

    public function getComments($handle)
    {
        $comments = [];
        $channel = $this->getChannel($handle);
        $args = [
            'order' => 'time',
            'allThreadsRelatedToChannelId' => $channel->id,
            'maxResults' => 100,
            'textFormat' => 'plainText'
        ];

        $complete = false;

        do {
            $response = $this->youtube->commentThreads->listCommentThreads('id,replies,snippet', $args);
            $comments = array_merge($comments, $this->parseCommentResponse($response, $channel));

            if (!$response->nextPageToken) {
                $complete = true;
            } else {
                $args['pageToken'] = $response->nextPageToken;
            }
        } while(!$complete);

        return $comments;
    }

    public function getStatuses($pageUID, $since, $handle)
    {
        $videos = array();
        $channels = $this->youtube->channels->listChannels('contentDetails',['id' => $pageUID])->getItems();
        if($channels && count($channels)) {
            $videoDetails = array();
            $playlistId = $channels[0]->contentDetails->relatedPlaylists->uploads;
            $args = ['playlistId' => $playlistId, 'maxResults' => 50];
            $complete = false;
            while (!$complete) {
                $playlistItemResponse = $this->youtube->playlistItems
                    ->listPlaylistItems('snippet', $args);
                $playlistItems = $playlistItemResponse->getItems();

                $videoIds = array();
                foreach ($playlistItems as $p) {
                    if ($p->snippet->resourceId->kind === 'youtube#video') {
                        $videoIds[] = $p->snippet->resourceId->videoId;
                    }
                }

                $q = ['id' => implode(',', $videoIds)];
                $details = $this->youtube->videos->listVideos('snippet,statistics', $q)->getItems();
                $videoDetails = array_merge($videoDetails, $details);

                if (!$playlistItemResponse->nextPageToken) {
                    $complete = true;
                } else {
                    $args['pageToken'] = $playlistItemResponse->nextPageToken;
                }
            }

            foreach ($videoDetails as $m) {
                $video = new YoutubeVideo();
                $video->id = $m->id;
                $video->comments = $m->statistics->commentCount ? $m->statistics->commentCount : 0;
                $video->likes = $m->statistics->likeCount ? $m->statistics->likeCount : 0;
                $video->dislikes = $m->statistics->dislikeCount ? $m->statistics->dislikeCount : 0;
                $video->views = $m->statistics->viewCount ? $m->statistics->viewCount : 0;
                $video->created_time = strtotime($m->snippet->publishedAt);
                $video->posted_by_owner = true;
                $video->permalink = 'https://www.youtube.com/watch?v=' . $video->id;
                $video->title = $m->snippet->title;
                $video->description = $m->snippet->description;

                $videos[] = $video;
            }
        }
        return $videos;
    }

    /**
     * @param Google_Service_YouTube_CommentThreadListResponse $response
     * @param Google_Service_YouTube_Channel $channel
     * @return array
     */
    protected function parseCommentResponse(Google_Service_YouTube_CommentThreadListResponse $response, Google_Service_YouTube_Channel $channel)
    {
        $comments = [];

        /** @var Google_Service_YouTube_CommentThread $thread */
        foreach ($response->getItems() as $thread) {
            $comments[] = $this->parseCommentThread($channel, $thread);

            /** @var Google_Service_YouTube_CommentThreadReplies $replies */
            $replies = $thread->getReplies();

            if ($replies) {
                foreach ($replies->getComments() as $reply) {
                    $comments[] = $this->parseCommentReply($channel, $reply);
                }
            }
        }

        return $comments;
    }

    /**
     * @param Google_Service_YouTube_Channel $channel
     * @param $thread
     * @return YoutubeComment
     */
    protected function parseCommentThread(Google_Service_YouTube_Channel $channel, $thread)
    {
        /** @var Google_Service_YouTube_CommentThreadSnippet $snippet */
        $snippet = $thread->getSnippet();
        /** @var Google_Service_YouTube_Comment $topLevelComment */
        $topLevelComment = $snippet->getTopLevelComment();
        /** @var Google_Service_YouTube_CommentSnippet $commentSnippet */
        $commentSnippet = $topLevelComment->getSnippet();

        $comment = new YoutubeComment;
        $comment->id = $topLevelComment->getId();
        $comment->created_time = date_create_from_format('Y-m-d\TH:i:s.u\Z', $commentSnippet->getPublishedAt())->getTimestamp();
        $comment->likes = $commentSnippet->getLikeCount();
        $comment->numberOfReplies = $snippet->getTotalReplyCount();
        $comment->authorChannelId = $commentSnippet->getAuthorChannelId() ? $commentSnippet->getAuthorChannelId()->value : null;
        $comment->in_response_to_status_uid = null;
        $comment->posted_by_owner = $comment->authorChannelId === $channel->getId() ? 1 : 0;
        $comment->rating = $commentSnippet->getViewerRating();
        $comment->message = $commentSnippet->getTextDisplay();
        $comment->videoId = $commentSnippet->videoId;
        return $comment;
    }

    /**
     * @param Google_Service_YouTube_Channel $channel
     * @param $reply
     * @return YoutubeComment
     */
    protected function parseCommentReply(Google_Service_YouTube_Channel $channel, $reply)
    {
        /** @var Google_Service_YouTube_Comment $reply */
        /** @var Google_Service_YouTube_CommentSnippet $replySnippet */
        $replySnippet = $reply->getSnippet();

        $comment = new YoutubeComment;
        $comment->id = $reply->getId();
        $comment->created_time = date_create_from_format('Y-m-d\TH:i:s.u\Z', $replySnippet->getPublishedAt())->getTimestamp();
        $comment->likes = $replySnippet->getLikeCount();
        $comment->numberOfReplies = null;
        $comment->authorChannelId = $replySnippet->getAuthorChannelId()->value;
        $comment->in_response_to_status_uid = $replySnippet->getParentId();
        $comment->posted_by_owner = $replySnippet->getAuthorChannelId()->value === $channel->getId() ? 1 : 0;
        $comment->rating = $replySnippet->getViewerRating();
        $comment->message = $replySnippet->getTextDisplay();
        return $comment;
    }
}