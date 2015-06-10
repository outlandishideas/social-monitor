<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class Presences extends Header {

    protected static $name = "presences";

    function __construct()
    {
        $this->label = "Presences";
        $this->sort = self::SORT_TYPE_NONE;
        $this->allowedTypes = array(self::MODEL_TYPE_CAMPAIGN);
        $this->display = self::DISPLAY_TYPE_SCREEN;
        $this->cellClasses[] = 'left-align';
    }


    /**
     * @param Model_Campaign $model
     * @return array
     */
    public function getTableCellValue($model)
    {
        $presences = array();
        foreach($model->getPresences() as $presence) {
            $template = '<a href="' . \Zend_View_Helper_Gatekeeper::PLACEHOLDER_URL . '"><span class="' . $presence->getPresenceSign() . ' fa-lg fa-fw"></span>' . $presence->getHandle() . '</a>';
            $urlArgs = array("controller" => "presence", "action" => "view", "id" => $presence->getId());
            $presences[$template] = $urlArgs;
        }
        return $presences;
    }


}