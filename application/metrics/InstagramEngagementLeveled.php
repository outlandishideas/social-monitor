<?php

class Metric_InstagramEngagementLeveled extends Metric_AbstractEngagement {

    protected static $name = "instagram_engagement";
    protected static $title = "Instagram Engagement Score";
    protected static $icon = "fa fa-instagram";
    protected static $gliding = false;
    protected static $targetOptions = [
        'ig_engagement_target_level_1',
        'ig_engagement_target_level_2',
        'ig_engagement_target_level_3',
        'ig_engagement_target_level_4',
        'ig_engagement_target_level_5'
    ];
    protected static $queryClassName = 'Outlandish\SocialMonitor\Engagement\Query\WeightedInstagramEngagementQuery';



}