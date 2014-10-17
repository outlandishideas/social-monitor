<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

abstract class Header_BadgeScores extends Header_Abstract {

    protected $sort = "data-value-numeric";

    public function getTableCellValue($model)
    {
        /** @var NewModel_Presence $model */
        $badges = $model->getBadges();
        $value = is_array($badges) && array_key_exists($this->getBadge(), $badges) ? round($badges[$this->getBadge()]) : "N/A";

        if(!is_numeric($value)) return "<span data-value='-1'>{$value}</span>";

        $color = Badge_Abstract::colorize($value);
        return "<span style='color:{$color};' data-value='{$value}'>{$value}<span>";
    }

}