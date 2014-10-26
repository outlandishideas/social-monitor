<?php

class Header_DigitalPopulationHealth extends Header_Abstract {

    protected static $name = "digital_population_health";

    function __construct()
    {
        $this->label = "Digital Population Health";
        $this->sort = self::SORT_TYPE_NUMERIC_DATA_VALUE;
        $this->allowedTypes = array(self::MODEL_TYPE_COUNTRY);
    }


    /**
     * @param Model_Country $model
     * @return mixed
     */
    public function getTableCellValue($model)
    {
        $value = $this->getValue($model);
        $text = $this->formatValue($value);

        if(is_null($value)) {
            $value = -1;
            $style = '';
        } else {
            $style = 'style="color:' . Util_Color::getDigitalPopulationHealthColor($value) . ';"';
        }

        return "<span $style data-value='{$value}'>{$text}<span>";
    }

    /**
     * @param Model_Country $model
     * @return null
     */
    function getValue($model = null)
    {
        return $model->getDigitalPopulationHealth();
    }

    function formatValue($value)
    {
        if (is_numeric($value)) {
            return round($value, 2) . '%';
        }
        return self::NO_VALUE;
    }


}