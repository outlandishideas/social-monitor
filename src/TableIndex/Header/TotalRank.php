<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class TotalRank extends BadgeRank {

    protected static $name = "total-rank";

    function __construct()
    {
        parent::__construct();
        $this->label = "Overall Rank";
        $this->description = "Overall Rank shows the rank of this presence or group when compared against others.";
    }

    /**
     * @return mixed
     */
    public function getBadgeName()
    {
        return \Badge_Total::getName() . "_rank";
    }


}