<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Outlandish\SocialMonitor\Helper\Gatekeeper;

class Options extends Header {

    const NAME = "options";

	public function __construct($translator)
    {
		parent::__construct($translator, self::NAME);
        $this->sort = self::SORT_TYPE_NONE;
        $this->display = self::DISPLAY_TYPE_SCREEN;
        $this->width = "160px";
        $this->cellClasses[] = 'left-align';
    }

    public function getTableCellValue($model)
    {
		$editStr = $this->translator->trans("buttons.common.edit");
		$deleteStr = $this->translator->trans("buttons.common.delete");
		$presencesStr = $this->translator->trans("buttons.common.manage-presences");

		if ($model instanceof \Model_Presence) {
            $options = array(
				$editStr => array('controller'=>'presence', 'action'=>'edit', 'id'=>$model->id),
				$deleteStr => array('controller'=>'presence', 'action'=>'delete', 'id'=>$model->id)
            );
        } else if ($model instanceof \Model_Country) {
            $options = array(
				$editStr => array('controller'=>'country', 'action'=>'edit', 'id'=>$model->id),
				$presencesStr => array('controller'=>'country', 'action'=>'manage', 'id'=>$model->id),
				$deleteStr => array('controller'=>'country', 'action'=>'delete', 'id'=>$model->id)
            );
        } else if ($model instanceof \Model_Group) {
            $options = array(
				$editStr => array('controller'=>'group', 'action'=>'edit', 'id'=>$model->id),
				$presencesStr => array('controller'=>'group', 'action'=>'manage', 'id'=>$model->id),
				$deleteStr => array('controller'=>'group', 'action'=>'delete', 'id'=>$model->id)
            );
        } else if ($model instanceof \Model_Region) {
			$countriesStr = $this->translator->trans("buttons.common.manage-countries");
			$options = array(
				$editStr => array('controller'=>'region', 'action'=>'edit', 'id'=>$model->id),
				$countriesStr => array('controller'=>'region', 'action'=>'manage', 'id'=>$model->id),
				$deleteStr => array('controller'=>'region', 'action'=>'delete', 'id'=>$model->id)
            );
        } else {
            $options = array();
        }

        $mappedOptions = array();
        foreach ($options as $key=>$args) {
            $mappedOptions['<a href="' . Gatekeeper::PLACEHOLDER_URL . '" class="button-bc button-' . strtolower($key) . '">' . $key . '</a>'] = $args;
        }
        return $mappedOptions;

    }


}