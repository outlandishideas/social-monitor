<?php

class Header_Compare extends Header_Abstract {

    protected static $name = "compare";

    function __construct()
    {
        $this->label = '<span class="icon-check"></span>';
        $this->description = 'Select all the presences that you would like to compare, and then click on the Compare Button above';
        $this->sort = 'checkbox';
        $this->csv = false;
    }


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