<?php

class Header_Name extends Header_Abstract {

    protected static $name = "name";

    function __construct()
    {
        $this->label = "Name";
        $this->allowedTypes = array(self::MODEL_TYPE_CAMPAIGN);
        $this->cellClasses[] = 'left-align';
    }


    /**
     * @param Model_Campaign $model
     * @return mixed
     */
    public function getValue($model = null)
    {
        if ($model instanceof Model_Country) {
            $baseUrl = Zend_Controller_Front::getInstance()->getBaseUrl();
            $imageUrl = $baseUrl.'/assets/img/flags/'.$model->getCountryCode().'.png';
            return '<span class="flag"><img src="'.$imageUrl.'" /></span> '.$model->display_name;
        }
        return $model->display_name;
    }


}