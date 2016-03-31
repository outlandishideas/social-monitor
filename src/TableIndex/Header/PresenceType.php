<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Model_Presence;

class PresenceType extends Header {

	const NAME = "presence-type";

	public function __construct($translator)
    {
		parent::__construct($translator, self::NAME);
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
        $type = $model->getType();
        return $type ? $type->getTitle() : 'N/A';
    }
}