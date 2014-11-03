<?php

class Model_Group extends Model_Campaign {

    const ICON_TYPE = 'icon-th-large';

	public static $campaignType = '0';

    public function fromArray($data) {
        if (array_key_exists('presences', $data)) {
            unset($data['presences']);
        }
        parent::fromArray($data);
    }

}
