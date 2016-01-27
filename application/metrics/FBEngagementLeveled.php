<?php

class Metric_FBEngagementLeveled extends Metric_AbstractEngagement {

    protected static $name = "facebook_engagement";
    protected static $title = "Facebook Engagement Score";
    protected static $icon = "fa fa-facebook-square";
    protected static $gliding = false;
    protected static $targetOptions = [
        'fb_engagement_target_level_1',
        'fb_engagement_target_level_2',
        'fb_engagement_target_level_3',
        'fb_engagement_target_level_4',
        'fb_engagement_target_level_5'
    ];
    protected static $queryClassName = 'Outlandish\SocialMonitor\Engagement\Query\WeightedFacebookEngagementQuery';

}