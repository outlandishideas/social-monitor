<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

class Header_ReachRank extends Header_Badges {

    protected static $name = "reach-rank";
    protected $label = "Reach Rank";
    protected $description = "Reach Rank shows the rank of this presence or group when compared against others.";
    protected $csv = true;

    /**
     * @return mixed
     */
    public function getBadge()
    {
        return Badge_Reach::getName() . "_rank";
    }

}