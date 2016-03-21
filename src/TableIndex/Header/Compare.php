<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Model_Presence;

class Compare extends Header {

    protected static $name = "compare";

    function __construct($translator)
    {
        parent::__construct($translator);
        $this->sort = self::SORT_TYPE_CHECKBOX;;
        $this->display = self::DISPLAY_TYPE_SCREEN;
    }


    /**
     * @param Model_Presence $model
     * @return string
     */
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