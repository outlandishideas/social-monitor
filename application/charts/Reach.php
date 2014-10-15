<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 14/10/2014
 * Time: 12:56
 */

class Chart_Reach extends Chart_Badge {

    protected static $title = "KPI: Reach";
    protected static $description;
    protected static $name = "reach";

    protected $yLabel = "Reach Score";

    public function getDataColumns()
    {
        return array(
            Badge_Reach::getName()
        );
    }


}