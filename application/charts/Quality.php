<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 14/10/2014
 * Time: 12:56
 */

class Chart_Quality extends Chart_Badge {

    protected static $title = "KPI: Quality";
    protected static $description;
    protected static $name = "quality";

    protected $yLabel = "Quality Score";

    public function getDataColumns()
    {
        return array(
            Badge_Quality::getName()
        );
    }


}