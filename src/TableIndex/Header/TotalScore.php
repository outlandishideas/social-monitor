<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class TotalScore extends BadgeScore {

    protected static $name = "total-score";

    public function getBadgeName()
    {
        return \Badge_Total::NAME;
    }


}