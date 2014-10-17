<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

class Header_Options extends Header_Abstract {

    protected static $name = "options";
    protected $label = "Options";
    protected $sort = null;
    protected $csv = false;

    public function getTableCellValue($model)
    {
        switch(get_class($model)){
            case "NewModel_Presence":
                $options = array(
                    '<a href="%url%"><span class="fa fa-eye fa-fw"></span> View Presence</a>' => array('action'=>'view', 'id'=>$model->id),
                    '<a href="%url%"><span class="fa fa-wrench fa-fw"></span> Edit Presence</a>' => array('action'=>'edit', 'id'=>$model->id)
                );
                break;
            case "Model_Country":
                $options = array(
                    '<a href="%url%"><span class="fa fa-eye fa-fw"></span> View Country</a>' => array('action'=>'view', 'id'=>$model->id),
                    '<a href="%url%"><span class="fa fa-wrench fa-fw"></span> Edit Country</a>' => array('action'=>'edit', 'id'=>$model->id),
                    '<a href="%url%"><span class="fa fa-sliders fa-fw"></span> Presences</a>' => array('action'=>'manage', 'id'=>$model->id)
                );
                break;
            case "Model_Group":
                $options = array(
                    '<a href="%url%"><span class="fa fa-eye fa-fw"></span> View SBU</a>' => array('action'=>'view', 'id'=>$model->id),
                    '<a href="%url%"><span class="fa fa-wrench fa-fw"></span> Edit SBU</a>' => array('action'=>'edit', 'id'=>$model->id),
                    '<a href="%url%"><span class="fa fa-sliders fa-fw"></span> Presences</a>' => array('action'=>'manage', 'id'=>$model->id)
                );
                break;
            default:
                $options = array();
                break;
        }

        return $options;

    }


}