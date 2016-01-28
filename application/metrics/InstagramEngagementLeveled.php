<?php

class Metric_InstagramEngagementLeveled extends Metric_AbstractEngagement {

    protected static $name = "instagram_engagement";
    protected static $title = "Instagram Engagement Score";
    protected static $icon = "fa fa-instagram";
    public $target = 0.75;
    protected static $gliding = false;
    protected static $queryClassName = 'Outlandish\SocialMonitor\Engagement\Query\WeightedInstagramEngagementQuery';



}