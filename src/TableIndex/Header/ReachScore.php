<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class ReachScore extends BadgeScore {

    protected static $name = "reach-score";

    function __construct($translator)
    {
        parent::__construct($translator);
    }

    /**
     * @return mixed
     */
    public function getBadgeName()
    {
        return \Badge_Reach::getInstance()->getName();
    }


}