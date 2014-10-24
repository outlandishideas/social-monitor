<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

class Header_Presences extends Header_Abstract {

    protected static $name = "presences";

    function __construct()
    {
        $this->label = "Presences";
        $this->description = 'The Digital Population is based on internet penetration in the country.';
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
        foreach($model->getPresences() as $presence) {
            $presences["<a href='%url%'><span class='{$presence->getPresenceSign()} fa-lg fa-fw'></span>{$presence->getHandle()}</a>"] = array("controller" => "presence", "action" => "view", "id" => $presence->getId());
        }
        return $presences;
    }


}