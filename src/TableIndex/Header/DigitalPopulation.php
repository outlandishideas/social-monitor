<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Model_Country;

class DigitalPopulation extends Header {

    protected static $name = "digital-population";

    function __construct($translator)
    {
        parent::__construct($translator);
        $this->sort = self::SORT_TYPE_NUMERIC_DATA_VALUE;
        $this->allowedTypes = array(self::MODEL_TYPE_COUNTRY);
    }


    public function getTableCellValue($model)
    {
        $value = $this->getValue($model);
        $text = $this->formatValue($value);

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