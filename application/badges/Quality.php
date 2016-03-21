<?php

class Badge_Quality extends Badge_Abstract
{
    protected static $instance;

    protected function __construct(PDO $db = null)
    {
        parent::__construct($db);
        $this->metrics = array(
            Metric_SignOff::getInstance(),
            Metric_Relevance::getInstance(),
            Metric_Branding::getInstance(),
            Metric_ActionsPerDay::getInstance(),
            Metric_ResponseTimeNew::getInstance(),
            Metric_LikesPerPost::getInstance(),
            Metric_LikesPerView::getInstance()
        );
    }
}