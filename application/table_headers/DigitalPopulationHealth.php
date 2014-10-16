<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

class Header_DigitalPopulationHealth extends Header_Abstract {

    protected static $name = "presences";
    protected $label = "Presences";
    protected $csv = false;

    /**
     * @param Model_Country $model
     * @return mixed
     */
    public function getTableCellValue($model)
    {
        $value = $model->getDigitalPopulationHealth();
        return is_numeric($value) ? number_format(round($value)) : "N/A";
    }
}