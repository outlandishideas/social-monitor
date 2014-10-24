<?php

class Header_TotalScore extends Header_BadgeScores {

    protected static $name = "total-score";

    function __construct()
    {
        parent::__construct();
        $this->label = "Overall Score";
        $this->description = "Overall Score shows the combined scores of the three badges, Reach, Engagement and Quality.";
        $this->csv = true;
    }

    /**
     * @return mixed
     */
    public function getBadge()
    {
        return Badge_Total::getName();
    }


}