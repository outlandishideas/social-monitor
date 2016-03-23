<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class ReachRank extends BadgeRank {

    protected static $name = "reach-rank";

    /**
     * @return mixed
     */
    public function getBadgeName()
    {
        return \Badge_Reach::NAME . "_rank";
    }

}