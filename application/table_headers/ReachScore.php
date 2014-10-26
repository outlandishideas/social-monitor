<?php

class Header_ReachScore extends Header_BadgeScore {

    protected static $name = "reach-score";

    function __construct()
    {
        parent::__construct();
        $this->label = "Reach Score";
        $this->description = "Reach Score shows the score for the combined measures that measure a presences engagement";
    }

    /**
     * @return mixed
     */
    public function getBadgeName()
    {
        return Badge_Reach::getName();
    }


}