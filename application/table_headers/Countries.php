<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

class Header_Countries extends Header_Abstract {

    protected static $name = "countries";
    protected $label = "Countries";
    protected $description = 'The countries in this region.';
    protected $sort = 'fuzzy-numeric';
    protected $csv = true;

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