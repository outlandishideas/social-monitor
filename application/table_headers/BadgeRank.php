<?php

abstract class Header_BadgeRank extends Header_Abstract {

    function __construct()
    {
        $this->sort = self::SORT_TYPE_NUMERIC;
        $this->allowedTypes = array(self::MODEL_TYPE_PRESENCE, self::MODEL_TYPE_CAMPAIGN);
    }

    /**
     * @return string
     */
    abstract public function getBadgeName();

    /**
     * @param Model_Presence|Model_Campaign $model
     * @throws RuntimeException
     * @return float|null|string
     */
    function getValue($model = null)
    {
        if ($model instanceof Model_Presence) {
            $badges = $model->getBadges();
        } else if ($model instanceof Model_Campaign) {
            $badges = $model->getBadges();
        } else {
            throw new RuntimeException('invalid model');
        }
        $badgeName = $this->getBadgeName();
        if (is_array($badges) && array_key_exists($badgeName, $badges)) {
            return floatval($badges[$badgeName]);
        }
        return null;
    }

    function formatValue($value)
    {
        if (is_numeric($value)) {
            return round($value);
        }
        return self::NO_VALUE;
    }


}