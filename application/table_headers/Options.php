<?php

class Header_Options extends Header_Abstract {

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
        switch(get_class($model)){
            case "NewModel_Presence":
                $options = array(
                    'View' => array('controller'=>'presence', 'action'=>'view', 'id'=>$model->id),
                    'Edit' => array('controller'=>'presence', 'action'=>'edit', 'id'=>$model->id),
                    'Delete' => array('controller'=>'presence', 'action'=>'delete', 'id'=>$model->id)
                );
                break;
            case "Model_Country":
                $options = array(
                    'View' => array('controller'=>'country', 'action'=>'view', 'id'=>$model->id),
                    'Edit' => array('controller'=>'country', 'action'=>'edit', 'id'=>$model->id),
                    'Presences' => array('controller'=>'country', 'action'=>'manage', 'id'=>$model->id)
                );
                break;
            case "Model_Group":
                $options = array(
                    'View' => array('controller'=>'group', 'action'=>'view', 'id'=>$model->id),
                    'Edit' => array('controller'=>'group', 'action'=>'edit', 'id'=>$model->id),
                    'Presences' => array('controller'=>'group', 'action'=>'manage', 'id'=>$model->id)
                );
                break;
            case 'Model_Region':
                $options = array(
                    'Edit' => array('controller'=>'region', 'action'=>'edit', 'id'=>$model->id),
                    'Countries' => array('controller'=>'region', 'action'=>'manage', 'id'=>$model->id),
					'Delete' => array('controller'=>'region', 'action'=>'delete', 'id'=>$model->id)
                );
                break;
            default:
                $options = array();
                break;
        }

        $mappedOptions = array();
        foreach ($options as $key=>$args) {
            $mappedOptions['<a href="' . Zend_View_Helper_Gatekeeper::PLACEHOLDER_URL . '" class="button-bc button-' . strtolower($key) . '">' . $key . '</a>'] = $args;
        }
        return $mappedOptions;

    }


}