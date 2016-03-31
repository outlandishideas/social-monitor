<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Model_Campaign;
use Model_Presence;

class TargetAudience extends Header {

    const NAME = "target-audience";

    public function __construct($translator)
    {
		parent::__construct($translator, self::NAME);
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