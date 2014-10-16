<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

class Header_CurrentAudience extends Header_Abstract {

    protected static $name = "current-audience";
    protected $label = "Current Audience";
    protected $sort = "fuzzy-numeric";

    public function getTableCellValue($model)
    {
        /** @var  NewModel_Presence $model */
        $current = $model->getPopularity();
        return is_numeric($current) ? number_format(round($current)) : "N/A";
    }


}