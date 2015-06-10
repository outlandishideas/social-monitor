<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class CountryCount extends Header {

    protected static $name = "country-count";

    function __construct()
    {
        $this->label = "Countries";
        $this->description = 'The number of countries.';
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