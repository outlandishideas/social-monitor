<?php

class Header_Branding extends Header_Abstract {

    protected static $name = "branding";

    function __construct()
    {
        $this->label = 'Branding';
        $this->description = 'Branding shows whether a presence meets the British Council branding guidelines for social media presences.';
        $this->sort = "data-value-numeric";
        $this->requiredType = 'presence';
    }


    public function getTableCellValue($model)
    {
        $value = $this->getValue($model);
        $class = $value==1 ? "value-good" : "value-bad";
        return "<span class='fa fa-lg fa-circle {$class}' data-value='{$value}'></span>";
    }

    /**
     * @param NewModel_Presence $model
     * @return int|null
     */
    function getValue($model = null)
    {
        return $model->getBranding() ? 1 : 0;
    }


}