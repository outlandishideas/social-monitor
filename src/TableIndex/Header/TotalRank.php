<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class TotalRank extends BadgeRank {

    protected static $name = "total-rank";

    /**
     * @return mixed
     */
    public function getBadgeName()
    {
        return \Badge_Total::NAME . "_rank";
    }


}