<?php

class CampaignController extends BaseController
{
	/**
	 * Lists all campaigns
	 * @permission list_campaign
	 */
	public function indexAction() {

		$this->view->title = 'Campaigns';
		$this->view->campaigns = $this->view->user->campaigns;
	}

    /**
     * Views a specific campaign page
     * @permission view_facebook_page
     */
    public function viewAction()
    {
        $page = Model_Campaign::fetchById($this->_request->id);

        $this->view->title = $page->name;
        $this->view->page = $page;
        $this->view->defaultLineId = self::makeLineId('Model_Campaign', $page->id);
    }

	/**
	 * If the user is assigned to the given campaign, switches to it
	 * @permission select_campaign
	 */
	public function selectAction()
	{
		if ($this->_request->id) {
			if (!in_array($this->_request->id, $this->view->user->campaignIds)) {
				$this->_helper->FlashMessenger(array('error' => 'You are not assigned to that campaign'));
			} else {
				$campaign = Model_Campaign::fetchById($this->_request->id);
				$this->view->user->last_campaign_id = $campaign->id;
				$this->view->user->save();
			}
			$this->_helper->redirector->gotoSimple('index', $this->_request->forward ?: 'index');
		}
		$this->_helper->layout()->setLayout('notabs');
	}

	/**
	 * Creates a new campaign
	 * @permission create_campaign
	 */
	public function newAction()
	{
		// do exactly the same as in editAction, but with a different title
		$this->editAction();
		$this->view->title = 'New Campaign';
		$this->_helper->viewRenderer->setScriptAction('edit');
	}

	/**
	 * Edits/creates a campaign
	 * @permission edit_campaign
	 */
	public function editAction()
	{
		if ($this->_request->action == 'edit') {
			$editingCampaign = Model_Campaign::fetchById($this->_request->id);
		} else {
			$editingCampaign = new Model_Campaign();
		}

		$this->validateData($editingCampaign);

		$this->view->allUsers = Model_User::fetchAll();
		if ($this->_request->isPost()) {
			$oldTimeZone = $editingCampaign->timezone;
			$editingCampaign->fromArray($this->_request->getParams());

			$errorMessages = array();
			if (!$this->_request->name) {
				$errorMessages[] = 'Please enter a campaign name';
			}

			if ($errorMessages) {
				foreach ($errorMessages as $message) {
					$this->_helper->FlashMessenger(array('error' => $message));
				}
			} else {
				try {
					$editingCampaign->save();

					$this->_helper->FlashMessenger(array('info' => 'Campaign saved'));
					$this->_helper->redirector->gotoSimple('index');
				} catch (Exception $ex) {
					if (strpos($ex->getMessage(), '23000') !== false) {
						$this->_helper->FlashMessenger(array('error' => 'Campaign name already taken'));
					} else {
						$this->_helper->FlashMessenger(array('error' => $ex->getMessage()));
					}
				}
			}
		}

		$utc = new DateTimeZone('UTC');
		$dt = new DateTime('now', $utc);

		$zoneInfo = array();
		foreach (DateTimeZone::listIdentifiers() as $tz) {
			$current_tz = new DateTimeZone($tz);
			$offset = $current_tz->getOffset($dt);
			$transition = $current_tz->getTransitions($dt->getTimestamp(), $dt->getTimestamp());
			$abbr = $transition[0]['abbr'];
			list($continent, $city) = $tz == 'UTC' ? array('UTC', 'UTC') : explode('/', $tz);

			$hours = $offset / 3600;
			$remainder = $offset % 3600;
			$sign = $hours > 0 ? '+' : '-';
			$hour = (int)abs($hours);
			$minutes = (int)abs($remainder / 60);

			if ($hour == 0 AND $minutes == 0) {
				$sign = ' ';
			}
			$displayOffset = $sign . str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutes, 2, '0');

			$zoneInfo[] = array(
				'name' => $tz,
				'abbr' => $abbr,
				'offset' => $offset,
				'display' => $displayOffset,
				'city' => str_replace('_', ' ', $city),
				'continent' => $continent
			);
		}

		uasort($zoneInfo, function($a, $b) { return $a['offset'] - $b['offset']; });

		$this->view->zones = array();
		foreach ($zoneInfo as $info) {
			$this->view->zones[$info['name']] = "$info[display] $info[city], $info[continent] ($info[abbr])";
		}

		$this->view->editingCampaign = $editingCampaign;
		$this->view->title = 'Edit Campaign';
	}

	/**
	 * Deletes a campaign
	 * @permission delete_campaign
	 */
	public function deleteAction() {
		$campaign = Model_Campaign::fetchById($this->_request->id);

		if ($this->_request->isPost()) {
			$campaign->delete();
			$this->_helper->FlashMessenger(array('info' => 'Campaign deleted'));
			$this->_helper->redirector->gotoSimple('index');
		}

		$this->view->title = 'Delete Campaign';
		$this->view->campaignToDelete = $campaign;
	}

	/**
	 * Redirects to twitter to request access to an account for use in fetching tweets
	 * @permission twitter_oauth
	 */
	public function oauthAction() {
		$this->_redirect($this->getOauthUrl());
	}

	/**
	 * Called after authenticating with twitter. Authorises with the campaign
	 * @permission twitter_callback
	 */
	public function callbackAction() {
		if ($this->_request->denied) {
			$this->_helper->FlashMessenger(array('info' => 'Authorization cancelled'));
		} else {
			$twitterUser = $this->handleOauthCallback($this->view->campaign);
			if ($twitterUser) {
				$this->_helper->FlashMessenger(array('info' => 'Authorized campaign with Twitter user @' . $twitterUser->screen_name));
			}
		}

		$this->_helper->redirector->gotoSimple('index', 'index');
	}

	/**
	 * Deauthorises the app against twitter
	 * @permission twitter_deauth
	 */
	public function deauthAction() {
		$this->view->campaign->token_id = null;
		$this->view->campaign->save();
		if (isset($this->_request->return_url)) {
			$this->redirect($this->_request->return_url);
		} else {
			$this->_helper->redirector->gotoSimple('index', 'index');
		}
	}
}

