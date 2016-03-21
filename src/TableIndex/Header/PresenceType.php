<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Enum_PresenceType;
use Model_Presence;

class PresenceType extends Header {

    protected static $name = "presence-type";

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
     * @param Model_Presence $model
     * @return string
     */
    public function getValue($model = null)
    {
        /** @var Enum_PresenceType $region */
        $type = $model->getType();
        return $type ? $type->getTitle() : 'N/A';
    }
}