<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class TotalScore extends BadgeScore {

    protected static $name = "total-score";

    function __construct()
    {
        parent::__construct();
        $this->label = "Overall Score";
        $this->description = "Overall Score shows the combined scores of the three badges, Reach, Engagement and Quality.";
    }

    public function getBadgeName()
    {
        return \Badge_Total::getName();
    }


}