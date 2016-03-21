<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Badge_Reach;

class ReachRank extends BadgeRank {

    protected static $name = "reach-rank";

    function __construct($translator)
    {
        parent::__construct($translator);
    }

    /**
     * @return mixed
     */
    public function getBadgeName()
    {
        return Badge_Reach::getInstance()->getName() . "_rank";
    }

}