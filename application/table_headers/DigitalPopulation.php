<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

class Header_DigitalPopulation extends Header_Abstract {

    protected static $name = "digital-population";
    protected $label = "Digital Population";

    /**
     * @param Model_Country $model
     * @return mixed
     */
    public function getTableCellValue($model)
    {
        $value = $model->getDigitalPopulation();
        return is_numeric($value) ? number_format(round($value)) : "N/A";
    }


}