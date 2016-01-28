<?php

class Metric_LinkedinEngagementLeveled extends Metric_AbstractEngagement {

    protected static $name = "linkedin_engagement";
    protected static $title = "LinkedIn Engagement Score";
    protected static $icon = "fa fa_linkedin";
    protected static $gliding = false;
    public $target = 0.25;
    protected static $queryClassName = 'Outlandish\SocialMonitor\Engagement\Query\WeightedLinkedinEngagementQuery';

}