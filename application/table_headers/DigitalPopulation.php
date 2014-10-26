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
        $text = $this->formatValue($model);

        if(is_null($value)) {
            $value = -1;
        }

        return "<span data-value='{$value}'>{$text}<span>";
    }

    /**
     * @param Model_Country $model
     * @return mixed
     */
    function getValue($model = null)
    {
        return $model->getDigitalPopulation();
    }

    function formatValue($value) {
        if (is_numeric($value)) {
            return number_format(round($value));
        }
        return self::NO_VALUE;
    }

}