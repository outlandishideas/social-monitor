<?php

class Header_CurrentAudience extends Header_Abstract {

    protected static $name = "current-audience";

    function __construct()
    {
        $this->label = "Current Audience";
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
        $current = $model->getPopularity();
        return is_numeric($current) ? number_format(round($current)) : "N/A";
    }


}