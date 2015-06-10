<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class Country extends Header {

    protected static $name = "country";

    function __construct()
    {
        $this->label = "Country";
        $this->allowedTypes = array(self::MODEL_TYPE_COUNTRY);
    }


    /**
     * @param Model_Country $model
     * @return string
     */
    public function getValue($model = null)
    {
        return $model->country;
    }


}