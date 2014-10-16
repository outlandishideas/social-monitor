<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

class Header_TotalRank extends Header_Badges {

    protected static $name = "total-rank";
    protected $label = "Overall Rank";
    protected $description = "Overall Rank shows the rank of this presence or group when compared against others.";
    protected $csv = true;

    /**
     * @return mixed
     */
    public function getBadge()
    {
        return Badge_Total::getName() . "_rank";
    }


}