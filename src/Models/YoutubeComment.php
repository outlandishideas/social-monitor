<?php

namespace Outlandish\SocialMonitor\Models;

class YoutubeComment extends Status {

    /** @var string */
    public $rating;
    /** @var string */
    public $videoId;
    /** @var string */
    public $authorChannelId;
    /** @var integer */
    public $numberOfReplies;
}