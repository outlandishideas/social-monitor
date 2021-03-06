<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Model_Presence;

class ParentName extends Header {

    const NAME = "parent-name";

	public function __construct($translator)
    {
		parent::__construct($translator, self::NAME);
        $this->allowedTypes = array(self::MODEL_TYPE_CAMPAIGN);
        $this->cellClasses[] = 'left-align';
    }


    /**
     * @param Model_Presence $model
     * @return mixed
     */
    public function getValue($model = null)
    {
        if (!($model instanceof Model_Presence)) {
            return null;
        }

        $owner = $model->getOwner();

        if (!$owner) {
            return null;
        }

        $value = new \stdClass();
        $value->controller = $owner instanceof \Model_Country ? 'country' : 'group';
        $value->name = $owner->display_name;
        $value->id = $owner->id;

        return $value;
    }


}