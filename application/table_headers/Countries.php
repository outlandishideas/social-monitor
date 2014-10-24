<?php

class Header_Countries extends Header_Abstract {

    protected static $name = "countries";

    function __construct()
    {
        $this->label = "Countries";
        $this->description = 'The countries in this region.';
        $this->sort = 'fuzzy-numeric';
        $this->csv = true;
    }


    /**
     * @param Model_Campaign $model
     * @return array
     */
    public function getTableCellValue($model)
    {
        $presences = array();
        foreach($model->getCountries() as $country) {
            $presences["<a href='%url%'><span class='fa-lg fa-fw'></span>{$country->getName()}</a>"] = array("controller" => "country", "action" => "view", "id" => $country->getId());
        }
        return $presences;
    }


}