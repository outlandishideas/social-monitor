<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

abstract class Header_Badges extends Header_Abstract {

    protected $sort = "numeric";

    public function getTableCellValue($model)
    {
        /** @var NewModel_Presence $model */
        $badges = $model->getBadges();
        return is_array($badges) && array_key_exists($this->getBadge(), $badges) ? round($badges[$this->getBadge()]) : "N/A";
    }

    /**
     * @return mixed
     */
    abstract public function getBadge();

}