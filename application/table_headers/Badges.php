<?php

abstract class Header_Badges extends Header_Abstract {


    function __construct()
    {
        $this->sort = "numeric";
        $this->requiredType = 'presence';
    }


    public function getTableCellValue($model)
    {
        return $this->getValue($model);
    }

    /**
     * @return mixed
     */
    abstract public function getBadge();

    /**
     * @param NewModel_Presence $model
     * @return float|null|string
     */
    function getValue($model = null)
    {
        $badges = $model->getBadges();
        return is_array($badges) && array_key_exists($this->getBadge(), $badges) ? round($badges[$this->getBadge()]) : "N/A";
    }


}