<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

class Header_Presences extends Header_Abstract {

    protected static $name = "presences";
    protected $label = "Digital Population";
    protected $description = 'The Digital Population is based on internet penetration in the country.';
    protected $sort = 'fuzzy-numeric';
    protected $csv = true;

    /**
     * @param Model_Campaign $model
     * @return array
     */
    public function getTableCellValue($model)
    {
        return $model->getPresences();
    }


}