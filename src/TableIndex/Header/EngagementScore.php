<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class EngagementScore extends BadgeScore {

    protected static $name = "engagement-score";

    function __construct($translator)
    {
        parent::__construct($translator);
    }

    /**
     * @return mixed
     */
    public function getBadgeName()
    {
        return \Badge_Engagement::getInstance()->getName();
    }

}