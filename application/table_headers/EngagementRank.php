<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

class Header_EngagementRank extends Header_Badges {

    protected static $name = "engagement-rank";
    protected $label = "Engagement Rank";
    protected $description = "Engagement Rank shows the rank of this presence or group when compared against others.";
    protected $csv = true;

    /**
     * @return mixed
     */
    public function getBadge()
    {
        return Badge_Engagement::getName() . "rank";
    }

}