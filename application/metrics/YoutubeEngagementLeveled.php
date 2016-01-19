<?php

class Metric_YoutubeEngagementLeveled extends Metric_AbstractEngagement {

    protected static $name = "youtube_engagement";
    protected static $title = "Youtube Engagement Score";
    protected static $icon = "fa fa-youtube";
    protected static $gliding = false;
    protected static $targetOptions = [
        'yt_engagement_target_level_1',
        'yt_engagement_target_level_2',
        'yt_engagement_target_level_3',
        'yt_engagement_target_level_4',
        'yt_engagement_target_level_5'
    ];
    protected static $queryClassName = 'Outlandish\SocialMonitor\Engagement\Query\WeightedYoutubeEngagementQuery';

}