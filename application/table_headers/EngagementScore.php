<?php

class Header_EngagementScore extends Header_BadgeScores {

    protected static $name = "engagement-score";

    function __construct()
    {
        parent::__construct();
        $this->label = "Engagement Score";
        $this->description = "Engagement Score shows the score for the combined measures that measure a presences engagement";
        $this->csv = true;
    }


    /**
     * @return mixed
     */
    public function getBadge()
    {
        return Badge_Engagement::getName();
    }

}