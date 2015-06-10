<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class Branding extends Header {

    protected static $name = "branding";

    function __construct()
    {
        $this->label = 'Branding';
        $this->description = 'Branding shows whether a presence meets the British Council branding guidelines for social media presences.';
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
     * @return int|null
     */
    function getValue($model = null)
    {
        return $model->getBranding() ? 1 : 0;
    }


}