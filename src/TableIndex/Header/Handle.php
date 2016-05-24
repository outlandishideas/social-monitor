<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Model_Presence;

class Handle extends Header {

    const NAME = "handle";

	public function __construct($translator)
    {
		parent::__construct($translator, self::NAME);
        $this->allowedTypes = array(self::MODEL_TYPE_PRESENCE);
        $this->cellClasses[] = 'left-align';
    }

    /**
     * @param Model_Presence $model
     * @return mixed|string
     */
    public function getTableCellValue($model)
    {
        $handle = $this->getValue($model);
        $sign = $model->getPresenceSign();
        $engagment = $model->getEngagementScore();
        $value = "<span class=\"white-background fixed-width $sign fa-fw\"></span> $handle";

        return $value;
    }

    /**
     * @param Model_Presence $model
     * @return null
     */
    function getValue($model = null)
    {
        return htmlspecialchars($model->getHandle());
    }


}