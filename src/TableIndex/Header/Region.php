<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Model_Country;
use Model_Presence;
use Model_Region;

class Region extends Header {

    protected static $name = "region";

    function __construct($translator)
    {
        parent::__construct($translator);
        $this->allowedTypes = array(self::MODEL_TYPE_COUNTRY, self::MODEL_TYPE_PRESENCE);
    }

    protected function isHidden()
    {
        return true;
    }


    /**
     * @param Model_Country|Model_Presence $model
     * @return string
     */
    public function getValue($model = null)
    {
        /** @var Model_Region $region */
        $region = $model->getRegion();
        return $region ? $region->getName() : '';
    }
}