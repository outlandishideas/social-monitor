<?php

class Header_Countries extends Header_Abstract {

    protected static $name = "countries";

    function __construct()
    {
        $this->label = "Countries";
        $this->description = 'The countries in this region.';
        $this->sort = self::SORT_TYPE_NONE;
        $this->allowedTypes = array(self::MODEL_TYPE_REGION);
        $this->display = self::DISPLAY_TYPE_SCREEN;
        $this->cellClasses[] = 'left-align';
    }


    /**
     * @param Model_Region $model
     * @return array
     */
    public function getTableCellValue($model)
    {
        $presences = array();
        $baseUrl = Zend_Controller_Front::getInstance()->getBaseUrl();
        if (isset($_SERVER['SCRIPT_NAME'])) {
            if (($pos = strripos($baseUrl, basename($_SERVER['SCRIPT_NAME']))) !== false) {
                $baseUrl = substr($baseUrl, 0, $pos);
            }
        }
        foreach($model->getCountries() as $country) {
            $imageUrl = $baseUrl . '/assets/img/flags/' . $country->getCountryCode() . '.png';
            $template = '<a href="' . Zend_View_Helper_Gatekeeper::PLACEHOLDER_URL . '" class="entity country"><span class="flag"><img src="' . $imageUrl . '" /></span> ' . $country->getName() . '</a>';
            $urlArgs = array("controller" => "country", "action" => "view", "id" => $country->id);
            $presences[$template] = $urlArgs;
        }
        return $presences;
    }


}