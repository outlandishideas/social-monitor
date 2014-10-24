<?php

class Header_QualityRank extends Header_Badges {

    protected static $name = "quality-rank";

    function __construct()
    {
        parent::__construct();
        $this->label = "Quality Rank";
        $this->description = "Quality Rank shows the rank of this presence or group when compared against others.";
        $this->csv = true;
    }

    /**
     * @return mixed
     */
    public function getBadge()
    {
        return Badge_Quality::getName() . "_rank";
    }

}