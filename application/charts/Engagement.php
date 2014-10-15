<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 14/10/2014
 * Time: 12:56
 */

class Chart_Engagement extends Chart_Badge {

    protected static $title = "KPI: Engagement";
    protected static $description;
    protected static $name = "engagement";

    protected $yLabel = "Reach Score";

    public function getDataColumns()
    {
        return array(
            Badge_Engagement::getName()
        );
    }


}