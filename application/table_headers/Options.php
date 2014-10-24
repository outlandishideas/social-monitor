<?php

class Header_Options extends Header_Abstract {

    protected static $name = "options";

    function __construct()
    {
        $this->label = "Options";
        $this->sort = null;
        $this->csv = false;
        $this->width = "150px";
    }

    public function getTableCellValue($model)
    {
        switch(get_class($model)){
            case "NewModel_Presence":
                $options = array(
                    '<a href="%url%">View Presence</a>' => array('action'=>'view', 'id'=>$model->id),
                    '<a href="%url%">Edit Presence</a>' => array('action'=>'edit', 'id'=>$model->id)
                );
                break;
            case "Model_Country":
                $options = array(
                    '<a href="%url%">View Country</a>' => array('action'=>'view', 'id'=>$model->id),
                    '<a href="%url%">Edit Country</a>' => array('action'=>'edit', 'id'=>$model->id),
                    '<a href="%url%">Presences</a>' => array('action'=>'manage', 'id'=>$model->id)
                );
                break;
            case "Model_Group":
                $options = array(
                    '<a href="%url%">View SBU</a>' => array('action'=>'view', 'id'=>$model->id),
                    '<a href="%url%">Edit SBU</a>' => array('action'=>'edit', 'id'=>$model->id),
                    '<a href="%url%">Presences</a>' => array('action'=>'manage', 'id'=>$model->id)
                );
                break;
            default:
                $options = array();
                break;
        }

        return $options;

    }


}