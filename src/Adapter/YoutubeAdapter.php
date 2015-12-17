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
        $response = $this->youtube->channels->listChannels('id,snippet,statistics', ['forUsername' => $handle]);

        $items = $response->getItems();

        if (empty($items)) {
            throw new \Exception("Youtube Channel \"{$handle}\" not found.");
        }

        /** @var Google_Service_Youtube_Channel $channel */
        $channel = $items[0];
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

    public function getStatuses($pageUID, $since, $handle)
    {
        $videos = array();
        $channels = $this->youtube->channels->listChannels('contentDetails',['forUsername' => $handle])->getItems();
        if($channels && count($channels)) {
            $videoDetails = array();
            $playlistId = $channels[0]->contentDetails->relatedPlaylists->uploads;
            $args = ['playlistId' => $playlistId, 'maxResults' => 50];
            $complete = false;
            while(!$complete) {
                $playlistItemResponse = $this->youtube->playlistItems
                    ->listPlaylistItems('snippet',$args);
                $playlistItems = $playlistItemResponse->getItems();

                $videoIds = array();
                foreach ($playlistItems as $p) {
                    if ($p->snippet->resourceId->kind === 'youtube#video') {
                        $videoIds[] = $p->snippet->resourceId->videoId;
                    }
                }

                $q = ['id'=>implode(',',$videoIds)];
                $details = $this->youtube->videos->listVideos('snippet,statistics', $q)->getItems();
                $videoDetails = array_merge($videoDetails,$details);

                if(!$playlistItemResponse->nextPageToken) {
                    $complete = true;
                } else {
                    $args['pageToken'] = $playlistItemResponse->nextPageToken;
                }
            }

            foreach ($videoDetails as $d) {
                $video = new YoutubeVideo();
                $video->id = $d->id;
                $video->comments = $d->statistics->commentCount ? $d->statistics->commentCount : 0;
                $video->likes = $d->statistics->likeCount ? $d->statistics->likeCount : 0;
                $video->dislikes = $d->statistics->dislikeCount ? $d->statistics->dislikeCount : 0;
                $video->views = $d->statistics->viewCount ? $d->statistics->viewCount : 0;
                $video->created_time = strtotime($d->snippet->publishedAt);
                $video->posted_by_owner = true;
                $video->permalink = 'https://www.youtube.com/watch?v=' . $video->id;
                $video->title = $d->snippet->title;
                $video->description = $d->snippet->description;

                $videos[] = $video;
            }
        }
        return $videos;
    }
}