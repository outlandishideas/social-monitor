<?php

class Header_QualityScore extends Header_BadgeScores {

    protected static $name = "quality-score";

    function __construct()
    {
        parent::__construct();
        $this->label = "Quality Score";
        $this->description = "Quality Score shows the score for the combined measures that measure a presences engagement";
        $this->csv = true;
    }

    /**
     * @return mixed
     */
    public function getBadge()
    {
        return Badge_Quality::getName();
    }

}