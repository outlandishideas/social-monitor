<?php

class Header_ReachRank extends Header_Badges {

    protected static $name = "reach-rank";

    function __construct()
    {
        parent::__construct();
        $this->label = "Reach Rank";
        $this->description = "Reach Rank shows the rank of this presence or group when compared against others.";
        $this->csv = true;
    }

    /**
     * @return mixed
     */
    public function getBadge()
    {
        return Badge_Reach::getName() . "_rank";
    }

}