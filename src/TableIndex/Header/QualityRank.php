<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Badge_Quality;

class QualityRank extends BadgeRank {

    protected static $name = "quality-rank";

    function __construct($translator)
    {
        parent::__construct($translator);
    }

    /**
     * @return mixed
     */
    public function getBadgeName()
    {
        return Badge_Quality::getInstance()->getName() . "_rank";
    }

}