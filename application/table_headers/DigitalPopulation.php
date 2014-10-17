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
    protected $sort = "data-value-numeric";

    /**
     * @param Model_Country $model
     * @return mixed
     */
    public function getTableCellValue($model)
    {
        $value = $model->getDigitalPopulation();
        $value = is_numeric($value) ? $value : "N/A";

        if(!is_numeric($value)) return "<span data-value='-1'>{$value}</span>";

        $number = number_format(round($value));
        return "<span data-value='{$value}'>{$number}%<span>";
    }


}