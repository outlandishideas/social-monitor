<?php

class Header_PresenceCount extends Header_Abstract {

    protected static $name = "presence-count";

    function __construct()
    {
        $this->label = "Presences";
        $this->description = 'The number of presences.';
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