<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

class Header_Compare extends Header_Abstract {

    protected static $name = "compare";
    protected $label = '<span class="icon-check"></span>';
    protected $description = 'Select all the presences that you would like to compare, and then click on the Compare Button above';
    protected $sort = 'checkbox';
    protected $csv = false;

    public function getTableCellValue($model)
    {
        return "<input
                    type='checkbox'
                    id='presence-{$model->getId()}'
                    class='compare-checkbox'
                    data-name='{$model->getHandle()}'
                    name='presences'
                    value='{$model->id}'>";
    }


}