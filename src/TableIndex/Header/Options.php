<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class Options extends Header {

    protected static $name = "options";

    function __construct()
    {
        $this->label = "Options";
        $this->sort = self::SORT_TYPE_NONE;
        $this->display = self::DISPLAY_TYPE_SCREEN;
        $this->width = "160px";
        $this->cellClasses[] = 'left-align';
    }

    public function getTableCellValue($model)
    {
        if ($model instanceof \Model_Presence) {
            $options = array(
                'Edit' => array('controller'=>'presence', 'action'=>'edit', 'id'=>$model->id),
                'Delete' => array('controller'=>'presence', 'action'=>'delete', 'id'=>$model->id)
            );
        } else if ($model instanceof \Model_Country) {
            $options = array(
                'Edit' => array('controller'=>'country', 'action'=>'edit', 'id'=>$model->id),
                'Presences' => array('controller'=>'country', 'action'=>'manage', 'id'=>$model->id),
                'Delete' => array('controller'=>'country', 'action'=>'delete', 'id'=>$model->id)
            );
        } else if ($model instanceof \Model_Group) {
            $options = array(
                'Edit' => array('controller'=>'group', 'action'=>'edit', 'id'=>$model->id),
                'Presences' => array('controller'=>'group', 'action'=>'manage', 'id'=>$model->id),
                'Delete' => array('controller'=>'group', 'action'=>'delete', 'id'=>$model->id)
            );
        } else if ($model instanceof \Model_Region) {
            $options = array(
                'Edit' => array('controller'=>'region', 'action'=>'edit', 'id'=>$model->id),
                'Countries' => array('controller'=>'region', 'action'=>'manage', 'id'=>$model->id),
                'Delete' => array('controller'=>'region', 'action'=>'delete', 'id'=>$model->id)
            );
        } else {
            $options = array();
        }

        $mappedOptions = array();
        foreach ($options as $key=>$args) {
            $mappedOptions['<a href="' . \Zend_View_Helper_Gatekeeper::PLACEHOLDER_URL . '" class="button-bc button-' . strtolower($key) . '">' . $key . '</a>'] = $args;
        }
        return $mappedOptions;

    }


}