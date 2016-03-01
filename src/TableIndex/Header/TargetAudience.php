<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Model_Campaign;
use Model_Presence;

class TargetAudience extends Header {

    protected static $name = "target-audience";

    function __construct()
    {
        $this->label = "Target Audience";
        $this->sort = self::SORT_TYPE_NUMERIC_FUZZY;
        $this->allowedTypes = array(self::MODEL_TYPE_PRESENCE, self::MODEL_TYPE_CAMPAIGN);
    }

    /**
     * @param Model_Presence|Model_Campaign $model
     * @return null|string
     */
    function getValue($model = null)
    {
        return $model->getTargetAudience();
    }

    function formatValue($value)
    {
        if (is_numeric($value)) {
            return number_format(round($value));
        }
        return self::NO_VALUE;
    }


}