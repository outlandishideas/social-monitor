<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

class Header_TotalScore extends Header_Badges {

    protected static $name = "total-score";
    protected $label = "Overall Score";
    protected $description = "Overall Score shows the combined scores of the three badges, Reach, Engagement and Quality.";
    protected $csv = true;

    /**
     * @return mixed
     */
    public function getBadge()
    {
        return Badge_Total::getName();
    }


}