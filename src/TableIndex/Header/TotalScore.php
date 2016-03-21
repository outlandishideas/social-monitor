<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class TotalScore extends BadgeScore {

    protected static $name = "total-score";

    function __construct($translator)
    {
        parent::__construct($translator);
    }

    public function getBadgeName()
    {
        return \Badge_Total::getInstance()->getName();
    }


}