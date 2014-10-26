<?php

class Header_CurrentAudience extends Header_Abstract {

    protected static $name = "current-audience";

    function __construct()
    {
        $this->label = "Current Audience";
        $this->sort = self::SORT_TYPE_NUMERIC_FUZZY;
        $this->allowedTypes = array(self::MODEL_TYPE_PRESENCE);
    }

    /**
     * @param NewModel_Presence $model
     * @return string
     */
    function getValue($model = null)
    {
        $current = $model->getPopularity();
        return is_numeric($current) ? number_format(round($current)) : self::NO_VALUE;
    }


}