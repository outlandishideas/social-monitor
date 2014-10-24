<?php

class Header_DigitalPopulation extends Header_Abstract {

    protected static $name = "digital-population";

    function __construct()
    {
        $this->label = "Digital Population";
        $this->sort = "data-value-numeric";
    }


    /**
     * @param Model_Country $model
     * @return mixed
     */
    public function getTableCellValue($model)
    {
        $value = $this->getValue($model);

        if(!is_numeric($value)) {
            return "<span data-value='-1'>{$value}</span>";
        }

        $number = number_format(round($value));
        return "<span data-value='{$value}'>{$number}<span>";
    }

    /**
     * @param Model_Country $model
     * @return mixed
     */
    function getValue($model = null)
    {
        $value = $model->getDigitalPopulation();
        $value = is_numeric($value) ? $value : "N/A";
        return $value;
    }


}