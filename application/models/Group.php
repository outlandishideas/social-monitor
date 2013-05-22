<?php

class Model_Group extends Model_Campaign {

    const ICON_TYPE = 'icon-th-large';

    protected function fetch($clause = null, $args = array()) {
		if ($clause) {
			$clause .= ' AND ';
		}
		$clause .= ' is_country = 0';
		return parent::fetch($clause, $args);
	}

    public function fromArray($data) {
        if (array_key_exists('presences', $data)) {
            unset($data['presences']);
        }
        parent::fromArray($data);
    }

    public function groupInfo(){
        return array(
            'pages' => (object)array(
                'title' => 'Facebook Pages',
                'value' => count($this->getFacebookPages()),
            ),
            'handles' => (object)array(
                'title' => 'Twitter Accounts',
                'value' => count($this->getTwitterAccounts()),
            ),
            'notes' => (object)array(
                'title' => 'Notes',
                'value' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum lacus eros, vulputate non mollis sed, egestas ut enim. Sed hendrerit dui nibh. Morbi bibendum feugiat nulla a tempus. Nam mattis egestas nisl a pulvinar. Curabitur non libero quis dolor ultricies tincidunt suscipit condimentum justo.' //$this->notes
            )
        );
    }
}
