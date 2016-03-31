<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Model_Campaign;

class PresenceCount extends Header {

	const NAME = "presence-count";

	public function __construct($translator)
    {
		parent::__construct($translator, self::NAME);
        $this->sort = self::SORT_TYPE_NONE;
        $this->allowedTypes = array(self::MODEL_TYPE_CAMPAIGN);
        $this->display = self::DISPLAY_TYPE_CSV;
    }

    /**
     * @param Model_Campaign $model
     * @return null
     */
    function getValue($model = null)
    {
        return $model->getPresenceCount();
    }

}