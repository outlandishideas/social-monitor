<?php

class Model_Region extends Model_Campaign {

    const ICON_TYPE = 'icon-compass';

	public static $countryFilter = '2';

    public function fromArray($data) {
        if (array_key_exists('presences', $data)) {
            unset($data['presences']);
        }
        parent::fromArray($data);
    }

    public function regionInfo(){
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

    public function getCountries()
    {
        $ret = array();
        $sql = "SELECT `id` FROM `campaigns` WHERE `parent` = ".$this->id;
        $db = Zend_Registry::get('db')->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $countryId) {
            $ret[$countryId] = Model_Country::fetchById($countryId);
        }
        return $ret;
    }
}
