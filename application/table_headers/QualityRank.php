<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

class Header_QualityRank extends Header_Badges {

    protected static $name = "quality-rank";
    protected $label = "Quality Rank";
    protected $description = "Quality Rank shows the rank of this presence or group when compared against others.";
    protected $csv = true;

    /**
     * @return mixed
     */
    public function getBadge()
    {
        return Badge_Quality::getName() . "_rank";
    }

}