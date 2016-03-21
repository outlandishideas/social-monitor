<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class TotalRank extends BadgeRank {

    protected static $name = "total-rank";

    function __construct($translator)
    {
        parent::__construct($translator);
    }

    /**
     * @return mixed
     */
    public function getBadgeName()
    {
        return \Badge_Total::getInstance()->getName() . "_rank";
    }


}