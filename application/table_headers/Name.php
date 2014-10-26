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
        return $model->display_name;
    }


}