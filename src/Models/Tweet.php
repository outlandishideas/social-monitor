<?php

namespace Outlandish\SocialMonitor\Models;

class Tweet extends Status {

    public $html;
    public $in_response_to_user_uid;
    public $isMention;
    public $share_count; //retweets

}