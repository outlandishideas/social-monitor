<?php

class Header_DigitalPopulationHealth extends Header_Abstract {

    protected static $name = "digital_population_health";

    function __construct()
    {
        $this->label = "Digital Population Health";
        $this->sort = "data-value-numeric";
        $this->csv = false;
    }


    /**
     * @param Model_Country $model
     * @return mixed
     */
    public function getTableCellValue($model)
    {
        $value = $model->getDigitalPopulationHealth();
        $value = is_numeric($value) ? round($value, 2) : "N/A";

        if(!is_numeric($value)) return "<span data-value='-1'>{$value}</span>";

        $color = Util_Color::getDigitalPopulationHealthColor($value);
        return "<span style='color:{$color};' data-value='{$value}'>{$value}%<span>";
    }
}