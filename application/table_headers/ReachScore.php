<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

class Header_ReachScore extends Header_BadgeScores {

    protected static $name = "reach-score";
    protected $label = "Reach Score";
    protected $description = "Reach Score shows the score for the combined measures that measure a presences engagement";
    protected $csv = true;

    /**
     * @return mixed
     */
    public function getBadge()
    {
        return Badge_Reach::getName();
    }


}