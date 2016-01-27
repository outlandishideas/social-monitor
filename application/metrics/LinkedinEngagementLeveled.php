<?php

class Metric_LinkedinEngagementLeveled extends Metric_AbstractEngagement {

    protected static $name = "linkedin_engagement";
    protected static $title = "LinkedIn Engagement Score";
    protected static $icon = "fa fa_linkedin";
    protected static $gliding = false;
    public $target = 0.1;
    protected static $targetOptions = [
        'in_engagement_target_level_1',
        'in_engagement_target_level_2',
        'in_engagement_target_level_3',
        'in_engagement_target_level_4',
        'in_engagement_target_level_5'
    ];
    protected static $queryClassName = 'Outlandish\SocialMonitor\Engagement\Query\WeightedLinkedinEngagementQuery';

}