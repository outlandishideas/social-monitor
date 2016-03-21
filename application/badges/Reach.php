<?php

class Badge_Reach extends Badge_Abstract
{
    protected static $instance;

    protected function __construct(PDO $db = null)
    {
        parent::__construct($db);
        $this->metrics = array(
            Metric_Popularity::getInstance(),
            Metric_PopularityTime::getInstance()
        );
    }

}