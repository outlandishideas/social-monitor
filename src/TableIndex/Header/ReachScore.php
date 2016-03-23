<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class ReachScore extends BadgeScore {

    protected static $name = "reach-score";

    /**
     * @return mixed
     */
    public function getBadgeName()
    {
        return \Badge_Reach::NAME;
    }


}