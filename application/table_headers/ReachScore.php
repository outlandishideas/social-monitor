<?php

class Header_ReachScore extends Header_BadgeScores {

    protected static $name = "reach-score";

    function __construct()
    {
        parent::__construct();
        $this->label = "Reach Score";
        $this->description = "Reach Score shows the score for the combined measures that measure a presences engagement";
        $this->csv = true;
    }

    /**
     * @return mixed
     */
    public function getBadge()
    {
        return Badge_Reach::getName();
    }


}