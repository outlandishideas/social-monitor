<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class QualityScore extends BadgeScore {

    protected static $name = "quality-score";

    /**
     * @return mixed
     */
    public function getBadgeName()
    {
        return \Badge_Quality::NAME;
    }

}