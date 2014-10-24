<?php

class Header_EngagementRank extends Header_Badges {

    protected static $name = "engagement-rank";

    function __construct()
    {
        parent::__construct();
        $this->label = "Engagement Rank";
        $this->description = "Engagement Rank shows the rank of this presence or group when compared against others.";
        $this->csv = true;
    }


    /**
     * @return mixed
     */
    public function getBadge()
    {
        return Badge_Engagement::getName() . "rank";
    }

}