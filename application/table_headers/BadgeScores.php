<?php

abstract class Header_BadgeScores extends Header_Abstract {

    function __construct()
    {
        $this->sort = "data-value-numeric";
        $this->requiredType = 'presence';
    }


    public function getTableCellValue($model)
    {
        $value = $this->getValue($model);

        if(!is_numeric($value)) {
            return "<span data-value='-1'>{$value}</span>";
        }

        $color = Badge_Abstract::colorize($value);
        return "<span style='color:{$color};' data-value='{$value}'>{$value}<span>";
    }

    abstract function getBadge();

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