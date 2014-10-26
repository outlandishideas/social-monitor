<?php

class Header_QualityScore extends Header_BadgeScore {

    protected static $name = "quality-score";

    function __construct()
    {
        parent::__construct();
        $this->label = "Quality Score";
        $this->description = "Quality Score shows the score for the combined measures that measure a presences engagement";
    }

    /**
     * @return mixed
     */
    public function getBadgeName()
    {
        return Badge_Quality::getName();
    }

}