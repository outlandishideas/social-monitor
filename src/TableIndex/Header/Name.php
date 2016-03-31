<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Model_Campaign;
use Model_Country;
use Zend_Controller_Front;

class Name extends Header {

    const NAME = "name";

	public function __construct($translator)
    {
        parent::__construct($translator, self::NAME);
        $this->allowedTypes = array(self::MODEL_TYPE_CAMPAIGN);
        $this->cellClasses[] = 'left-align';
    }


    /**
     * @param Model_Campaign $model
     * @return mixed
     */
    public function getValue($model = null)
    {
		$value = $model->display_name;
        if ($model instanceof Model_Country) {
            $value = '<div class="sm-flag flag-' . $model->getCountryCode() . '"></div> ' . $value;
        }
        return $value;
    }


}