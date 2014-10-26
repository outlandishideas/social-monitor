<?php

abstract class Header_BadgeScore extends Header_Abstract {

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

        if($value == self::NO_VALUE) {
            $dataValue = -1;
            $style = '';
        } else {
            $dataValue = $value;
            $color = Badge_Abstract::colorize($value);
            $style = 'style="color:' . $color . '"';
        }

        return "<span $style data-value='{$dataValue}'>{$value}<span>";
    }

    /**
     * @param NewModel_Presence|Model_Campaign $model
     * @throws RuntimeException
     * @return float|null|string
     */
    function getValue($model = null)
    {
        if ($model instanceof NewModel_Presence) {
            $badges = $model->getBadges();
        } else if ($model instanceof Model_Campaign) {
            $badges = $model->getBadges();
        } else {
            throw new RuntimeException('invalid model');
        }
        $badgeName = $this->getBadgeName();
        if (is_array($badges) && array_key_exists($badgeName, $badges)) {
            return round($badges[$badgeName]);
        }
        return self::NO_VALUE;
    }


}