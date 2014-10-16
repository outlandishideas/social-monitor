<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

class Header_Handle extends Header_Abstract {

    protected static $name = "handle";
    protected $label = 'Handle';
    protected $description = 'Select all the presences that you would like to compare, and then click on the Compare Button above';

    public function getTableCellValue($model)
    {
        return "<span class='{$model->getPresenceSign()} fa-lg fa-fw'></span> {$model->getHandle()}";
    }


}