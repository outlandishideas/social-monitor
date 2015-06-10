<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class PercentTargetAudience extends Header {

    protected static $name = "percent-target-audience";

    function __construct()
    {
        $this->label = "Percentage of Target Audience";
        $this->sort = self::SORT_TYPE_NUMERIC_FUZZY;
        $this->allowedTypes = array(self::MODEL_TYPE_REGION);
    }

    /**
     * @param Model_Region $model
     * @return null|string
     */
    function getValue($model = null)
    {
        return $model->getPercentTargetAudience();
    }

    function formatValue($value)
    {
        if (is_numeric($value)) {
            return round($value, 2).'%';
        }
        return self::NO_VALUE;
    }


}