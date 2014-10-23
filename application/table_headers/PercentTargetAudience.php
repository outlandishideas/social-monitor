<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

class Header_PercentTargetAudience extends Header_Abstract {

    protected static $name = "percent-target-audience";
    protected $label = "Percentage of Target Audience";
    protected $sort = "fuzzy-numeric";

    public function getTableCellValue($model)
    {
        $target = $model->getPercentTargetAudience();
        return is_numeric($target) ? round($target, 2).'%' : "N/A";
    }


}