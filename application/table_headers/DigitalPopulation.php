<?php

class Header_DigitalPopulation extends Header_Abstract {

    protected static $name = "digital-population";

    function __construct()
    {
        $this->label = "Digital Population";
        $this->sort = self::SORT_TYPE_NUMERIC_DATA_VALUE;
        $this->allowedTypes = array(self::MODEL_TYPE_COUNTRY);
    }


    public function getTableCellValue($model)
    {
        $value = $this->getValue($model);

        if($value == self::NO_VALUE) {
            $dataValue = -1;
            $text = $value;
        } else {
            $text = $value;
            $dataValue = $value;
        }

        return "<span data-value='{$dataValue}'>{$text}<span>";
    }

    /**
     * @param Model_Country $model
     * @return mixed
     */
    function getValue($model = null)
    {
        $value = $model->getDigitalPopulation();
        return is_numeric($value) ? number_format(round($value)) : self::NO_VALUE;
    }


}