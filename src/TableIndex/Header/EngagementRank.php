<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class EngagementRank extends BadgeRank {

    protected static $name = "engagement-rank";

    function __construct()
    {
        parent::__construct();
        $this->label = "Engagement Rank";
        $this->description = "Engagement Rank shows the rank of this presence or group when compared against others.";
    }

    /**
     * @return mixed
     */
    public function getBadgeName()
    {
        return Badge_Engagement::getName() . "rank";
    }

}