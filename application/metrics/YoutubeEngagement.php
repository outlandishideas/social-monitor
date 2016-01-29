<?php

class Metric_YoutubeEngagement extends Metric_AbstractEngagement {

    protected static $name = "youtube_engagement";
    protected static $title = "Youtube Engagement Score";
    protected static $icon = "fa fa-youtube";
    public $target = 0.08;
    protected static $gliding = false;
    protected static $queryClassName = 'Outlandish\SocialMonitor\Engagement\Query\WeightedYoutubeEngagementQuery';

}