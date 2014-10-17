<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

class Header_TargetAudience extends Header_Abstract {

    protected static $name = "target-audience";
    protected $label = "Target Audience";
    protected $sort = "fuzzy-numeric";

    public function getTableCellValue($model)
    {
        $target = $model->getTargetAudience();
        return is_numeric($target) ? number_format(round($target)) : "N/A";
    }


}