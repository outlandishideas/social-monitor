<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Outlandish\SocialMonitor\Helper\Gatekeeper;

class Presences extends Header {

    const NAME = "presences";

	public function __construct($translator)
    {
		parent::__construct($translator, self::NAME);
        $this->sort = self::SORT_TYPE_NONE;
        $this->allowedTypes = array(self::MODEL_TYPE_CAMPAIGN);
        $this->display = self::DISPLAY_TYPE_SCREEN;
        $this->cellClasses[] = 'left-align';
    }


    /**
     * @param \Model_Campaign $model
     * @return array
     */
    public function getTableCellValue($model)
    {
        $presences = array();
        foreach($model->getPresences() as $presence) {
            $template = '<a href="' . Gatekeeper::PLACEHOLDER_URL . '"><span class="white-background fixed-width ' . $presence->getPresenceSign() . ' fa-fw"></span>' . htmlspecialchars($presence->getHandle()) . '</a>';
            $urlArgs = array("controller" => "presence", "action" => "view", "id" => $presence->getId());
            $presences[$template] = $urlArgs;
        }
        return $presences;
    }
    
    
    
}