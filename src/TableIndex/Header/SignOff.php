<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class SignOff extends Header {

    protected static $name = "sign-off";

    function __construct()
    {
        $this->label = 'Sign Off';
        $this->description = 'Sign Off shows whether a presence has been signed off by the Head of Digital.';
        $this->sort = self::SORT_TYPE_NUMERIC_DATA_VALUE;
        $this->allowedTypes = array(self::MODEL_TYPE_PRESENCE);
    }

    public function getTableCellValue($model)
    {
        $value = $this->getValue($model);
        $class = $value==1 ? "value-good icon-ok-sign" : "value-bad icon-remove-sign";
        return "<span class='icon-background'><span class='fa fa-lg icon-large {$class}' data-value='{$value}'></span></span>";
    }

    /**
     * @param Model_Presence $model
     * @return null
     */
    function getValue($model = null)
    {
        return $model->getSignOff() ? 1 : 0;
    }


}