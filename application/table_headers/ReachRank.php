<?php

class Header_ReachRank extends Header_BadgeRank {

    protected static $name = "reach-rank";

    function __construct()
    {
        parent::__construct();
        $this->label = "Reach Rank";
        $this->description = "Reach Rank shows the rank of this presence or group when compared against others.";
    }

    /**
     * @return mixed
     */
    public function getBadgeName()
    {
        return Badge_Reach::getName() . "_rank";
    }

}