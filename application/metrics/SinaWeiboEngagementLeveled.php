<?php

class Metric_SinaWeiboEngagementLeveled extends Metric_AbstractEngagement {

    protected static $name = "sina_weibo_engagement";
    protected static $title = "Sina Weibo Engagement Score";
    protected static $icon = "fa fa-weibo";
    public $target = 0.25;
    protected static $gliding = false;
    protected static $queryClassName = 'Outlandish\SocialMonitor\Engagement\Query\WeightedSinaWeiboEngagementQuery';

}