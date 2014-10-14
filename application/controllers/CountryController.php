<?php

class CountryController extends CampaignController {

	public function init() {
		parent::init();
		$this->view->titleIcon = Model_Country::ICON_TYPE;
	}

	/**
	 * Lists all countries
	 * @user-level user
	 */
	public function indexAction() {
		/** @var Model_Country[] $countries */
		$countries = Model_Country::fetchAll();
		$presences = array();
		foreach (NewModel_PresenceFactory::getPresences() as $p) {
			$presences[$p->id] = $p;
		}
		$query = self::db()->prepare('SELECT c.id, cp.presence_id FROM campaigns AS c LEFT OUTER JOIN campaign_presences AS cp ON c.id = cp.campaign_id');
		$query->execute();
		$mapping = array();
		foreach ($query->fetchAll(PDO::FETCH_OBJ) as $row) {
			if (!isset($mapping[$row->id])) {
				$mapping[$row->id] = array();
			}
			$mapping[$row->id][] = $row->presence_id;
		}
		foreach ($countries as $country) {
			$country->getPresences($mapping, $presences);
		}
        $this->view->title = 'Countries';
        $this->view->tableHeaders = static::generateTableHeaders();
		$this->view->tableMetrics = self::tableMetrics();
		$this->view->countries = $countries;
        $this->view->badgeData = Model_Country::badgesData();
	}

	/**
	 * Views a specific country
	 * @user-level user
	 */
	public function viewAction()
	{
		/** @var Model_Country $country */
		$country = Model_Country::fetchById($this->_request->id);
		$this->validateData($country);

        $compareData = array();
        foreach($country->getPresences() as $presence){
            $compareData[$presence->id] = (object)array(
                'presence'=>$presence,
                'graphs'=>$this->graphs($presence)
            );
        }

		$this->view->badgePartial = $this->badgeDetails($country);

		$this->view->metricOptions = self::graphMetrics();
        $this->view->tableMetrics = self::tableMetrics();
        $this->view->compareData = $compareData;
		$this->view->title = $country->display_name;
		$this->view->titleInfo = array(
			'audience' => (object)array(
					'title' => 'Audience',
					'value' => number_format($country->audience),
				),
			'pages' => (object)array(
					'title' => 'Facebook Pages',
					'value' => count($country->getFacebookPages()),
				),
			'handles' => (object)array(
					'title' => 'Twitter Accounts',
					'value' => count($country->getTwitterAccounts()),
//			),
//			'notes' => (object)array(
//				'title' => 'Notes',
//				'value' => '' //$this->notes
			)
		);
        $this->view->country = $country;
        $this->view->badges = Model_Badge::$ALL_BADGE_TYPES;
	}

	/**
	 * Creates a new country
	 * @user-level manager
	 */
	public function newAction()
	{
		// do exactly the same as in editAction, but with a different title
		$this->editAction();
		$this->view->title = 'New Country';
		$this->view->titleIcon = 'icon-plus-sign';
		$this->_helper->viewRenderer->setScriptAction('edit');
	}

	/**
	 * Edits/creates a country
	 * @user-level user
	 */
	public function editAction()
	{
		if ($this->_request->action == 'edit') {
			$editingCountry = Model_Country::fetchById($this->_request->id);
            $this->view->showButtons = true;
		} else {
			$editingCountry = new Model_Country();
            $this->view->showButtons = false;
		}

		$this->validateData($editingCountry);

		$this->view->countryCodes = Model_Country::countryCodes();

		if ($this->_request->isPost()) {

			$editingCountry->fromArray($this->_request->getParams());

			$errorMessages = array();
			if (!$this->_request->display_name) {
				$errorMessages[] = 'Please enter a display name';
			}
			if (!$this->_request->country) {
				$errorMessages[] = 'Please select a country';
			}

            $editingCountry->penetration = max(0, $editingCountry->penetration);
            $editingCountry->penetration = min(100, $editingCountry->penetration);

			if ($errorMessages) {
				foreach ($errorMessages as $message) {
					$this->_helper->FlashMessenger(array('error' => $message));
				}
			} else {
				try {
					$editingCountry->save();

					$this->_helper->FlashMessenger(array('info' => 'Country saved'));
					$this->_helper->redirector->gotoSimple('index');
				} catch (Exception $ex) {
					if (strpos($ex->getMessage(), '23000') !== false) {
						$this->_helper->FlashMessenger(array('error' => 'Display name already taken'));
					} else {
						$this->_helper->FlashMessenger(array('error' => $ex->getMessage()));
					}
				}
			}
		}

//		$utc = new DateTimeZone('UTC');
//		$dt = new DateTime('now', $utc);
//
//		$zoneInfo = array();
//		foreach (DateTimeZone::listIdentifiers() as $tz) {
//			$current_tz = new DateTimeZone($tz);
//			$offset = $current_tz->getOffset($dt);
//			$transition = $current_tz->getTransitions($dt->getTimestamp(), $dt->getTimestamp());
//			$abbr = $transition[0]['abbr'];
//			list($continent, $city) = $tz == 'UTC' ? array('UTC', 'UTC') : explode('/', $tz);
//
//			$hours = $offset / 3600;
//			$remainder = $offset % 3600;
//			$sign = $hours > 0 ? '+' : '-';
//			$hour = (int)abs($hours);
//			$minutes = (int)abs($remainder / 60);
//
//			if ($hour == 0 AND $minutes == 0) {
//				$sign = ' ';
//			}
//			$displayOffset = $sign . str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutes, 2, '0');
//
//			$zoneInfo[] = array(
//				'name' => $tz,
//				'abbr' => $abbr,
//				'offset' => $offset,
//				'display' => $displayOffset,
//				'city' => str_replace('_', ' ', $city),
//				'continent' => $continent
//			);
//		}
//
//		uasort($zoneInfo, function($a, $b) { return $a['offset'] - $b['offset']; });
//
//		$this->view->zones = array();
//		foreach ($zoneInfo as $info) {
//			$this->view->zones[$info['name']] = "$info[display] $info[city], $info[continent] ($info[abbr])";
//		}

		$this->view->editingCountry = $editingCountry;
		$this->view->title = 'Edit Country';
		$this->view->titleIcon = 'icon-edit';
	}

    /**
     * Edits/creates a country
     * @user-level user
     */
    public function editAllAction()
    {

        $this->view->title = 'Edit All';
        $this->view->countries = Model_Country::fetchAll();
        $this->view->countryCodes = Model_Country::countryCodes();

        if ($this->_request->isPost()) {

            $result = $this->_request->getParams();

            $editingCountries = array();

            foreach($result as $k => $v){
                if(preg_match('|^([0-9]+)\_(.+)$|', $k, $matches)){
                    if(!array_key_exists($matches[1], $editingCountries)) $editingCountries[$matches[1]] = array('id' => $matches[1]);
                    $editingCountries[$matches[1]][$matches[2]] = $v;
                }
            }

            $errorMessages = array();

            $editedCountries = array();

//			$oldTimeZone = $editingCountry->timezone;
            foreach($editingCountries as $c){
                $editingCountry = Model_Country::fetchById($c['id']);
                $display_name = $editingCountry->display_name;
                $editingCountry->fromArray($c);

                if (!$c['display_name']) {
                    $errorMessages[] = 'Please enter a display name for '. $display_name;
                }
                if (!$c['country']) {
                    $errorMessages[] = 'Please select a country for '. $display_name;
                }

                $editedCountries[] = $editingCountry;

            }

            if ($errorMessages) {
                foreach ($errorMessages as $message) {
                    $this->_helper->FlashMessenger(array('error' => $message));
                }
            } else {
                try {
                    foreach($editedCountries as $country){
                        $country->save();
                    }

                    $this->_helper->FlashMessenger(array('info' => count($editedCountries) . ' Countries saved'));
                    $this->_helper->redirector->gotoSimple('index');

                } catch (Exception $ex) {
                    if (strpos($ex->getMessage(), '23000') !== false) {
                        $this->_helper->FlashMessenger(array('error' => 'Display name already taken'));
                    } else {
                        $this->_helper->FlashMessenger(array('error' => $ex->getMessage()));
                    }
                }
            }

        }

        $this->view->titleIcon = 'icon-edit';
    }

	/**
	 * Manages the presences that belong to a country
	 * @user-level manager
	 */
	public function manageAction() {
		/** @var Model_Country $country */
		$country = Model_Country::fetchById($this->_request->id);
		$this->validateData($country);

		if ($this->_request->isPost()) {
			$presenceIds = array();
			foreach ($this->_request->assigned as $ids) {
				foreach ($ids as $id) {
					$presenceIds[] = $id;
				}
			}
			$country->assignPresences($presenceIds);
			$this->_helper->FlashMessenger(array('info' => 'Country presences updated'));
			$this->_helper->redirector->gotoSimple('index');
		}

		$this->view->title = 'Manage Country Presences';
		$this->view->titleIcon = 'icon-tasks';
		$this->view->country = $country;
		$this->view->presences = $this->managePresencesList();
	}

	/**
	 * Deletes a country
	 * @user-level manager
	 */
	public function deleteAction() {
		$country = Model_Country::fetchById($this->_request->id);
		$this->validateData($country);

		if ($this->_request->isPost()) {
			$country->delete();
			$this->_helper->FlashMessenger(array('info' => 'Country deleted'));
		}
		$this->_helper->redirector->gotoSimple('index');
	}

    /**
     * Gets all of the graph data for the requested presence
     */
    public function badgeDataAction() {
        Zend_Session::writeClose(); // release session on long running actions

	    /** @var Model_Country $country */
        $country = Model_Country::fetchById($this->_request->id);

        $response = $country->badges();

        $this->apiSuccess($response);

    }

	public function downloadAction() {
		parent::downloadAsCsv('country_index', Model_Country::badgesData(), Model_Country::fetchAll(), self::tableIndexHeaders());
	}

    /**
     * function to generate tableIndexHeaders for tbe campaign index pages
     * refer to tableHeader() in GraphingController
     * @return array
     */
    public static function tableIndexHeaders() {

        $return = array(
            'name' => true,
            'country' => true,
            'total-rank' => true,
            'total-score' => true,
            'digital-population' => true,
            'digital-population-health' => true,
            'target-audience' => true
        );

        foreach(self::tableMetrics() as $name => $title){
            $return[$name] = true;
        }
        $return['presences'] = true;
        $return['options'] = false;

        return $return;
    }

}
