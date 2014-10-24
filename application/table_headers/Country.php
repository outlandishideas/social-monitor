<?php

class Header_Country extends Header_Abstract {

    protected static  $name = "country";

    function __construct()
    {
        $this->label = "Country";
        $this->csv = true;
    }


    /**
     * @param Model_Country $model
     * @return mixed
     */
    public function getTableCellValue($model)
    {
        return $model->country;
    }


}