<?php

class Header_TargetAudience extends Header_Abstract {

    protected static $name = "target-audience";

    function __construct()
    {
        $this->label = "Target Audience";
        $this->sort = "fuzzy-numeric";
        $this->requiredType = 'presence';
    }

    public function getTableCellValue($model)
    {
        return $this->getValue($model);
    }

    /**
     * @param NewModel_Presence $model
     * @return null|string
     */
    function getValue($model = null)
    {
        $target = $model->getTargetAudience();
        return is_numeric($target) ? number_format(round($target)) : "N/A";
    }


}