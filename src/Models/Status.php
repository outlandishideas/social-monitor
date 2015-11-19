<?php

namespace Outlandish\SocialMonitor\Models;

class Status {

    public $id;
    public $presence_id;
    public $message;
    public $created_time;
    public $permalink;
    public $posted_by_owner;
    public $needs_response;
    public $in_response_to_status_uid;
    public $links;
    public $likes;

}