<?php

namespace Outlandish\SocialMonitor\Models;

/**
 * Class PresenceMetadata
 *
 * Used to define what information an API adapter must return from the getMetadata() function.
 * There should not be any metadata fetched from an API that is not specified in this class.
 *
 * @package Outlandish\SocialMonitor\Models
 */
class PresenceMetadata {

    public $uid;
    public $name;
    public $page_url;
    public $image_url;
    public $popularity;

}