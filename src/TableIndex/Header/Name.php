<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Model_Campaign;
use Model_Country;

class Name extends Header {

    const NAME = "name";

	public function __construct($translator)
    {
        parent::__construct($translator, self::NAME);
        $this->allowedTypes = array(self::MODEL_TYPE_CAMPAIGN);
        $this->cellClasses[] = 'left-align';
    }

    public function getTableCellValue($model)
    {
        $value = $model->display_name;
        $formattedValue = $this->formatValue($value);
        if ($model instanceof Model_Country) {
            $value = '<div class="sm-flag flag-' . $this->formatValue($model->getCountryCode()) . '"></div> ' . $formattedValue;
        }
        
        return $formattedValue;
    }
    
    public function getValue($model = null)
    {
        return $model->display_name;
    }

    public function formatValue($value)
    {
        return htmlspecialchars($value);
    }

}