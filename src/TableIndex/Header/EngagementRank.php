<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Badge_Engagement;

class EngagementRank extends BadgeRank {

    protected static $name = "engagement-rank";

    /**
     * @return mixed
     */
    public function getBadgeName()
    {
        return Badge_Engagement::NAME . "_rank";
    }

}