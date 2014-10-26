<?php

class Header_TargetAudience extends Header_Abstract {

    protected static $name = "target-audience";

    function __construct()
    {
        $this->label = "Target Audience";
        $this->sort = self::SORT_TYPE_NUMERIC_FUZZY;
        $this->allowedTypes = array(self::MODEL_TYPE_PRESENCE, self::MODEL_TYPE_CAMPAIGN);
    }

    /**
     * @param NewModel_Presence|Model_Campaign $model
     * @return null|string
     */
    function getValue($model = null)
    {
        $target = $model->getTargetAudience();
        return is_numeric($target) ? number_format(round($target)) : self::NO_VALUE;
    }


}