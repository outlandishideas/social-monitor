<?php

class Header_SignOff extends Header_Abstract {

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
        $class = $value==1 ? "value-good" : "value-bad";
        return "<span class='fa fa-lg fa-circle {$class}' data-value='{$value}'></span>";
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