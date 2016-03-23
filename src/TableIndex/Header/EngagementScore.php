<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class EngagementScore extends BadgeScore {

    protected static $name = "engagement-score";

    /**
     * @return mixed
     */
    public function getBadgeName()
    {
        return \Badge_Engagement::NAME;
    }

}