<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

class Header_SignOff extends Header_Abstract {

    protected static $name = "sign-off";
    protected $label = 'Sign Off';
    protected $description = 'Sign Off shows whether a presence has been signed off by the Head of Digital.';
    protected $sort = "data-value-numeric";

    public function getTableCellValue($model)
    {
        $value = $model->getSignOff() ? 1 : 0;
        $class =$value==1 ? "value-good" : "value-bad";
        return "<span class='fa fa-lg fa-circle {$class}' data-value='{$value}'></span>";
    }


}