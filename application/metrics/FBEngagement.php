<?php

class Metric_FBEngagement extends Metric_AbstractEngagement {

    protected static $name = "facebook_engagement";
    protected static $title = "Facebook Engagement Score";
    protected static $icon = "fa fa-facebook-square";
    public $target = 0.25;
    protected static $gliding = false;
    protected static $queryClassName = 'Outlandish\SocialMonitor\Engagement\Query\WeightedFacebookEngagementQuery';


}