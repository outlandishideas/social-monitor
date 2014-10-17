<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

class Header_Branding extends Header_Abstract {

    protected static $name = "branding";
    protected $label = 'Branding';
    protected $description = 'Branding shows whether a presence meets the British Council branding guidelines for social media presences.';
    protected $sort = "data-value-numeric";

    public function getTableCellValue($model)
    {
        $value = $model->getBranding() ? 1 : 0;
        $class = $value==1 ? "value-good" : "value-bad";
        return "<span class='fa fa-lg fa-circle {$class}' data-value='{$value}'></span>";
    }

}