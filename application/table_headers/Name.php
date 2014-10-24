<?php

class Header_Name extends Header_Abstract {

    protected static $name = "name";

    function __construct()
    {
        $this->label = "Name";
        $this->csv = true;
    }


    /**
     * @param Model_Campaign $model
     * @return mixed
     */
    public function getTableCellValue($model)
    {
        return $model->display_name;
    }


}