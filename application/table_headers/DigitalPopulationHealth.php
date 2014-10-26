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

        if($value == self::NO_VALUE) {
            $dataValue = -1;
            $style = '';
        } else {
            $dataValue = $value;
            $color = Util_Color::getDigitalPopulationHealthColor($value);
            $style = 'style="color:' . $color . ';"';
        }

        return "<span $style data-value='{$dataValue}'>{$value}<span>";
    }

    /**
     * @param Model_Country $model
     * @return null
     */
    function getValue($model = null)
    {
        $value = $model->getDigitalPopulationHealth();
        return is_numeric($value) ? round($value, 2) . '%' : self::NO_VALUE;
    }


}