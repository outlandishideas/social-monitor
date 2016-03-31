<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Model_Region;

class PercentTargetAudience extends Header {

	const NAME = "percent-target-audience";

	public function __construct($translator)
    {
		parent::__construct($translator, self::NAME);
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