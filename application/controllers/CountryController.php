<?php

class CountryController extends CampaignController {

	/**
	 * Lists all countries
	 * @permission list_country
	 */
	public function indexAction() {

		$this->view->title = 'Countries';
		$this->view->tableMetrics = self::tableMetrics();
		$this->view->countries = Model_Country::fetchAll();
	}

	/**
	 * Views a specific country
	 * @permission view_country
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

        $this->view->metricOptions = self::graphMetrics();
        $this->view->tableMetrics = self::tableMetrics();
        $this->view->compareData = $compareData;
		$this->view->title = '<span class="icon-globe"></span> '. $country->display_name . ' <a href="#" class="accordian-btn" data-id="title-info"><span class="icon-caret-down icon-small"></span></a>';
		$this->view->titleInfo = $country->countryInfo();
        $this->view->country = $country;
	}

	/**
	 * Creates a new country
	 * @permission create_country
	 */
	public function newAction()
	{
		// do exactly the same as in editAction, but with a different title
		$this->editAction();
		$this->view->title = 'New Country';
		$this->_helper->viewRenderer->setScriptAction('edit');
	}

	/**
	 * Edits/creates a country
	 * @permission edit_country
	 */
	public function editAction()
	{
		if ($this->_request->action == 'edit') {
			$editingCountry = Model_Country::fetchById($this->_request->id);
            $this->showButtons = true;
		} else {
			$editingCountry = new Model_Country();
            $this->showButtons = false;
		}

		$this->validateData($editingCountry);

		$this->view->countryCodes = Model_Country::countryCodes();

		if ($this->_request->isPost()) {
//			$oldTimeZone = $editingCountry->timezone;
			$editingCountry->fromArray($this->_request->getParams());

			$errorMessages = array();
			if (!$this->_request->display_name) {
				$errorMessages[] = 'Please enter a display name';
			}
			if (!$this->_request->country) {
				$errorMessages[] = 'Please select a country';
			}

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
	}

	/**
	 * Manages the presences that belong to a country
	 * @permission manage_country
	 */
	public function manageAction() {
		/** @var Model_Country $country */
		$country = Model_Country::fetchById($this->_request->id);
		$this->validateData($country);

		if ($this->_request->isPost()) {
			$country->assignPresences($this->_request->presences);
			$this->_helper->FlashMessenger(array('info' => 'Country presences updated'));
			$this->_helper->redirector->gotoSimple('index');
		}

		$this->view->title = 'Manage Country Presences';
		$this->view->country = $country;
		$this->view->twitterPresences = Model_Presence::fetchAllTwitter();
		$this->view->facebookPresences = Model_Presence::fetchAllFacebook();
	}

	/**
	 * Deletes a country
	 * @permission delete_country
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
}
