<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Model_Country;

class Country extends Header {

    protected static $name = "country";

    function __construct($translator)
    {
        parent::__construct($translator);
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