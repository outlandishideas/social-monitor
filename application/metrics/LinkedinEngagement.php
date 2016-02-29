<?php

class Metric_LinkedinEngagement extends Metric_AbstractEngagement {

    protected static $name = "linkedin_engagement";
    protected static $title = "LinkedIn Engagement Score";
    protected static $icon = "fa fa_linkedin";
    protected static $gliding = false;
    public static $engagementTarget = 0.25;
    protected static $queryClassName = 'Outlandish\SocialMonitor\Engagement\Query\WeightedLinkedinEngagementQuery';

}