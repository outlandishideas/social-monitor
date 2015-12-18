<?php

namespace Outlandish\SocialMonitor\Adapter;

use DateTime;
use Exception_InstagramNotFound;
use Google_Service_YouTube_Channel;
use Google_Service_YouTube_ChannelSnippet;
use Google_Service_YouTube_ChannelStatistics;
use Google_Service_YouTube_ThumbnailDetails;
use Outlandish\SocialMonitor\InstagramApp;
use Outlandish\SocialMonitor\Models\InstagramStatus;
use Outlandish\SocialMonitor\Models\PresenceMetadata;
use Outlandish\SocialMonitor\Models\Status;
use Outlandish\SocialMonitor\Models\YoutubeVideo;

class YoutubeAdapter extends AbstractAdapter
{

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
     * @param string $handle
     * @return Google_Service_YouTube_Channel
     * @throws \Exception
     */
    private function getChannel($handle)
    {
        $response = $this->youtube->channels->listChannels('id,snippet,statistics', ['forUsername' => $handle]);

        /** @var Google_Service_Youtube_Channel[] $items */
        $items = $response->getItems();

        if (empty($items)) {
            throw new \Exception("Youtube Channel \"{$handle}\" not found.");
        }

        return $items[0];
    }

    public function getComments($videos)
    {
        $this->youtube->comments->listComments();
    }

    public function getStatuses($pageUID, $since, $handle)
    {
        $videos = array();
        $channel = $this->getChannel($handle);
        $playlistId = $channel->getContentDetails()->getRelatedPlaylists()->getUploads();
        $media = $this->youtube->playlistItems->listPlaylistItems('snippet', ['playlistId' => $playlistId])->getItems();
        $videoIds = array();
        foreach($media as $m) {
            if($m->snippet->resourceId->kind === 'youtube#video') {
                $videoIds[] = $m->snippet->resourceId->videoId;
            }
        }
        $q = ['id'=>implode(',',$videoIds)];
        $details = $this->youtube->videos->listVideos('snippet,statistics', $q)->getItems();

        foreach ($details as $m) {
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
        return $videos;
    }
}