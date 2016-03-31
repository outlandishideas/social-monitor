<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Model_Region;

class CountryCount extends Header {

	const NAME = "country-count";

    function __construct($translator)
    {
		parent::__construct($translator, self::NAME);
        $this->sort = self::SORT_TYPE_NONE;
        $this->allowedTypes = array(self::MODEL_TYPE_REGION);
        $this->display = self::DISPLAY_TYPE_CSV;
    }

    /**
     * @param Model_Region $model
     * @return null
     */
    function getValue($model = null)
    {
        return count($model->getCountries());
    }

}