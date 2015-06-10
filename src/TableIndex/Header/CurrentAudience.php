<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class CurrentAudience extends Header {

    protected static $name = "current-audience";

    function __construct()
    {
        $this->label = "Current Audience";
        $this->sort = self::SORT_TYPE_NUMERIC_FUZZY;
        $this->allowedTypes = array(self::MODEL_TYPE_PRESENCE);
    }

    /**
     * @param Model_Presence $model
     * @return string
     */
    function getValue($model = null)
    {
        return $model->getPopularity();
    }

    function formatValue($value)
    {
        if (is_numeric($value)) {
            return number_format(round($value));
        }
        return self::NO_VALUE;
    }

}