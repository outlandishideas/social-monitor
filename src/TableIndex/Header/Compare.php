<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class Compare extends Header {

    protected static $name = "compare";

    function __construct()
    {
        $this->label = '<span class="icon-check"></span>';
        $this->description = 'Select all the presences that you would like to compare, and then click on the Compare Button above';
        $this->sort = self::SORT_TYPE_CHECKBOX;;
        $this->display = self::DISPLAY_TYPE_SCREEN;
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