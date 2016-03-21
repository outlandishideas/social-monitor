<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Badge_Quality;

class QualityRank extends BadgeRank {

    protected static $name = "quality-rank";

    function __construct()
    {
        parent::__construct();
        $this->label = "Quality Rank";
        $this->description = "Quality Rank shows the rank of this presence or group when compared against others.";
    }

    /**
     * @return mixed
     */
    public function getBadgeName()
    {
        return Badge_Quality::getInstance()->getName() . "_rank";
    }

}