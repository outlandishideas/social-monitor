<?php

class Header_Handle extends Header_Abstract {

    protected static $name = "handle";

    function __construct()
    {
        $this->label = 'Handle';
        $this->description = 'Select all the presences that you would like to compare, and then click on the Compare Button above';
        $this->allowedTypes = array(self::MODEL_TYPE_PRESENCE);
        $this->cellClasses[] = 'left-align';
    }


    public function getTableCellValue($model)
    {
        $value = $this->getValue($model);
        return "<span class='{$model->getPresenceSign()} fa-lg fa-fw'></span> $value";
    }

    /**
     * @param NewModel_Presence $model
     * @return null
     */
    function getValue($model = null)
    {
        return $model->getHandle();
    }


}