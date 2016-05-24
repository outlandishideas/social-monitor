<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Outlandish\SocialMonitor\Helper\Gatekeeper;

class Countries extends Header {

    const NAME = "countries";

    function __construct($translator)
    {
		parent::__construct($translator, self::NAME);
        $this->sort = self::SORT_TYPE_NONE;
        $this->allowedTypes = array(self::MODEL_TYPE_REGION);
        $this->display = self::DISPLAY_TYPE_SCREEN;
        $this->cellClasses[] = 'left-align';
    }


    /**
     * @param \Model_Region $model
     * @return array
     */
    public function getTableCellValue($model)
    {
        $presences = array();
        foreach($model->getCountries() as $country) {
            $template = '<a href="' . Gatekeeper::PLACEHOLDER_URL . '" class="entity country"><div class="sm-flag flag-' . $country->getCountryCode() . '"></div> ' . htmlspecialchars($country->getName()) . '</a>';
            $urlArgs = array("controller" => "country", "action" => "view", "id" => $country->id);
            $presences[$template] = $urlArgs;
        }
        return $presences;
    }
}