<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

class Header_Name extends Header_Abstract {

    protected static $name = "name";
    protected $label = "Name";
    protected $csv;

    /**
     * @param Model_Campaign $model
     * @return mixed
     */
    public function getTableCellValue($model)
    {
        return $model->display_name;
    }


}