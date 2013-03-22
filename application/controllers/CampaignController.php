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

					//recalculate buckets if campaign timezone is changed
					if ($oldTimeZone != $editingCampaign->timezone) {
//						$zone = new DateTimeZone($editingCampaign->timezone);
//						$offset = $zone->getOffset(new DateTime()); //this is wrong as it doesn't account for summer time, probably doesn't matter
//						foreach ($editingCampaign->facebookPages as $page) $page->recalculateBuckets($offset);
//						foreach ($editingCampaign->twitterSearches as $search) $search->recalculateBuckets($offset);
//						foreach ($editingCampaign->twitterLists as $list) $list->recalculateBuckets($offset);
					}

					//save user assignments
					$allIds = array();
					foreach ($this->view->allUsers as $c) {
						$allIds[] = $c->id;
					}

					$currentIds = $editingCampaign->userIds;
					$newIds = $this->_request->user ? array_keys($this->_request->user) : array();
					foreach ($allIds as $id) {
						if (in_array($id, $newIds)) {
							if (!in_array($id, $currentIds)) {
								$editingCampaign->assignUser($id);
							}
						} else {
							if (in_array($id, $currentIds)) {
								$editingCampaign->unassignUser($id);
							}
						}
					}

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

		// unused - what was this for?
		/*
		function formatOffset($offset) {
			$hours = $offset / 3600;
			$remainder = $offset % 3600;
			$sign = $hours > 0 ? '+' : '-';
			$hour = (int)abs($hours);
			$minutes = (int)abs($remainder / 60);

			if ($hour == 0 AND $minutes == 0) {
				$sign = ' ';
			}
			return $sign . str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutes, 2, '0');

		}
		*/

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

	private function generateDateRange($year, $month) {
		$startDate = $year . '-' . $month . '-01';
		return array($startDate, date('Y-m-d', strtotime($startDate . ' +1 month -1 day')));
	}

	/**
	 * Shows stats about the campaign's openamplify usage
	 * @permission campaign_stats
	 */
	public function apiAction() {
		$campaign = Model_Campaign::fetchById($this->_request->id);

		$this->validateData($campaign);

		$this->view->title = 'Open Amplify API usage: ' . $campaign->name;

		$dates = array();
		foreach ($campaign->getAvailableApiDates() as $year=>$months) {
			$currentYear = array();
			foreach ($months as $month => $total) {
				$currentYear[$month] = array(
					'label' => date('F', strtotime('2012-' . $month . '-01')) . ' (' . number_format($total) . ')',
					'line-id' => implode(':', array('CampaignAPI', $campaign->id, $year . '-' . $month)),
					'date-range' => json_encode($this->generateDateRange($year, $month))
				);
			}
			$dates[$year] = $currentYear;
		}

		if ($dates) {
			$year = $this->_request->year;
			$month = $this->_request->month;
			if (!$year || !$month || !isset($dates[$year][$month])) {
				// choose the most recent month
				$year = array_pop(array_keys($dates));
				$month = array_pop(array_keys($dates[$year]));
			}
			$this->view->defaultLineId = implode(':', array('CampaignAPI', $campaign->id, $year . '-' . $month));
			$this->view->defaultDateRange = json_encode($this->generateDateRange($year, $month));
		}

		$this->view->availableDates = $dates;
	}

	/**
	 * Ajax action for fetching stats data for a particular month
	 */
	public function graphDataAction() {
		if (empty($this->_request->line_ids)) {
			$this->apiError('Missing line ID');
		}

		$lineId = is_array($this->_request->line_ids) ? $this->_request->line_ids[0] : $this->_request->line_ids;
		if (preg_match('/(\d+):(\d{4})-(\d{2})/', $lineId, $matches)) {
			$campaignId = $matches[1];
			$year = $matches[2];
			$month = $matches[3];

			$campaign = Model_Campaign::fetchById($campaignId);
			if (!$campaign) {
				$this->apiError('Campaign not found');
			}

			$stats = $campaign->getApiStats($year, $month);
			$graphData = array(
				'line_id' => $lineId,
				'name' => $lineId,
				'points' => array(array())
			);
			foreach ($stats as $date=>$count) {
				$graphData['points'][0][] = array('date'=>$date . ' 00:00:00', 'count'=>$count);
			}
			// add a second line segment for the quota, if specified
			if ($campaign->analysis_quota) {
				$quotaPoints = array();
				$dates = $this->generateDateRange($year, $month);
				foreach ($dates as $date) {
					$quotaPoints[] = array('date'=>$date . ' 00:00:00', 'count'=>$campaign->analysis_quota);
				}
				$graphData['points'][] = $quotaPoints;
			}
			$this->apiSuccess(array('graph-data'=>$graphData));
		} else {
			$this->apiError('Invalid line ID');
		}
	}
}

