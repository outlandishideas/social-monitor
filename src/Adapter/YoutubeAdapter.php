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
        $videos = [];
        return $videos;
    }
}