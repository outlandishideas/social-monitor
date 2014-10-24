<?php

class Header_TotalRank extends Header_Badges {

    protected static $name = "total-rank";

    function __construct()
    {
        parent::__construct();
        $this->label = "Overall Rank";
        $this->description = "Overall Rank shows the rank of this presence or group when compared against others.";
        $this->csv = true;
    }

    /**
     * @return mixed
     */
    public function getBadge()
    {
        return Badge_Total::getName() . "_rank";
    }


}