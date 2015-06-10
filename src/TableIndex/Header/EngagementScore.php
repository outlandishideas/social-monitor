<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class EngagementScore extends BadgeScore {

    protected static $name = "engagement-score";

    function __construct()
    {
        parent::__construct();
        $this->label = "Engagement Score";
        $this->description = "Engagement Score shows the score for the combined measures that measure a presences engagement";
    }

    /**
     * @return mixed
     */
    public function getBadgeName()
    {
        return Badge_Engagement::getName();
    }

}