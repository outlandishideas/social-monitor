<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

class Header_Country extends Header_Abstract {

    protected static  $name = "country";
    protected $label = "Country";
    protected $csv = true;

    /**
     * @param Model_Country $model
     * @return mixed
     */
    public function getTableCellValue($model)
    {
        return $model->country;
    }


}