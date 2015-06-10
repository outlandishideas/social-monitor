<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

abstract class BadgeScore extends Header {

    function __construct()
    {
        $this->sort = self::SORT_TYPE_NUMERIC_DATA_VALUE;
        $this->allowedTypes = array(self::MODEL_TYPE_PRESENCE, self::MODEL_TYPE_CAMPAIGN);
    }

    /**
     * @return string
     */
    abstract function getBadgeName();

    public function getTableCellValue($model)
    {
        $value = $this->getValue($model);
        $text = $this->formatValue($value);

        if(is_null($value)) {
            $value = -1;
            $style = '';
        } else {
            $style = 'style="color:' . Badge_Abstract::colorize($value) . '"';
        }

        return "<span $style data-value='{$value}'>{$text}</span>";
    }

    /**
     * @param Model_Presence|Model_Campaign $model
     * @throws \RuntimeException
     * @return float|null|string
     */
    function getValue($model = null)
    {
        if ($model instanceof \Model_Presence) {
            $badges = $model->getBadges();
        } else if ($model instanceof \Model_Campaign) {
            $badges = $model->getBadges();
        } else {
            throw new \RuntimeException('invalid model');
        }
        $badgeName = $this->getBadgeName();
        if (is_array($badges) && array_key_exists($badgeName, $badges)) {
            return floatval($badges[$badgeName]);
        }
        return null;
    }

    function formatValue($value) {
        if (is_numeric($value)) {
            return round($value);
        }
        return self::NO_VALUE;
    }
}