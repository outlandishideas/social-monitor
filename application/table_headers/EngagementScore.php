<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

class Header_EngagementScore extends Header_Badges {

    protected static $name = "engagement-score";
    protected $label = "Engagement Score";
    protected $description = "Engagement Score shows the score for the combined measures that measure a presences engagement";
    protected $csv = true;

    /**
     * @return mixed
     */
    public function getBadge()
    {
        return Badge_Engagement::getName();
    }

}