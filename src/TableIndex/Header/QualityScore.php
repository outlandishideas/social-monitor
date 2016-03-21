<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class QualityScore extends BadgeScore {

    protected static $name = "quality-score";

    function __construct($translator)
    {
        parent::__construct($translator);
    }

    /**
     * @return mixed
     */
    public function getBadgeName()
    {
        return \Badge_Quality::getInstance()->getName();
    }

}