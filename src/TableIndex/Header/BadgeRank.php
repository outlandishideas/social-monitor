<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Model_Campaign;
use Model_Presence;

/**
 * Base class for quality, reach, engagement and total rank headers
 */
abstract class BadgeRank extends Header {

	protected $badgeName;

    function __construct($translator, $name, $badgeName)
    {
        parent::__construct($translator, $name);
		$this->badgeName = $badgeName;
        $this->sort = self::SORT_TYPE_NUMERIC_FUZZY;
        $this->allowedTypes = array(self::MODEL_TYPE_PRESENCE, self::MODEL_TYPE_CAMPAIGN);
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
        $badgeName = $this->badgeName;
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