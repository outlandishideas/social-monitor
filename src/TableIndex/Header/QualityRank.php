<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Badge_Quality;

class QualityRank extends BadgeRank {

    protected static $name = "quality-rank";

    /**
     * @return mixed
     */
    public function getBadgeName()
    {
        return Badge_Quality::NAME . "_rank";
    }

}