<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Badge_Engagement;

class EngagementRank extends BadgeRank {

    protected static $name = "engagement-rank";

    function __construct($translator)
    {
        parent::__construct($translator);
    }

    /**
     * @return mixed
     */
    public function getBadgeName()
    {
        return Badge_Engagement::getInstance()->getName() . "_rank";
    }

}