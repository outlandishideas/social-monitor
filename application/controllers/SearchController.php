<?php

class SearchController extends SocialApiController
{
	/**
	 * Displays a list of all twitter searches and their available stats and actions
	 * @permission list_twitter_search
	 */
	public function indexAction() {
		$this->view->title = 'Twitter Searches';
		$this->view->searches = $this->view->campaign->twitterSearches;
		usort($this->view->searches, function($a, $b) {
			return strcasecmp(ltrim($a->name, '#@ '), ltrim($b->name, '@# '));
		});
	}

	/**
	 * Views a single twitter search
	 * @permission view_twitter_search
	 */
	public function viewAction()
	{
		$search = Model_TwitterSearch::fetchById($this->_request->id);
		$this->validateData($search);

		$this->view->title = 'Twitter Search: ' . $search->name;
		$this->view->search = $search;
		$this->view->defaultLineId = self::makeLineId('Model_TwitterSearch', $search->id);
	}

	/**
	 * Compares activity from multiple twitter searches
	 * @permission compare_twitter_search
	 */
	public function compareAction() {
		$this->view->title = 'Compare Searches';
		$this->view->searches = $this->view->campaign->twitterSearches;
	}

	/**
	 * Creates a new twitter search
	 * @permission create_twitter_search
	 */
	public function newAction()
	{
		// do exactly the same as in editAction, but with a different title
		$this->editAction();
		$this->view->title = 'New Twitter Search';
		$this->_helper->viewRenderer->setScriptAction('edit');
	}

	/**
	 * Edits an existing twitter search
	 * @permission edit_twitter_search
	 */
	public function editAction()
	{
		if ($this->_request->action == 'edit') {
			$editingSearch = Model_TwitterSearch::fetchById($this->_request->id);
		} else {
			$editingSearch = new Model_TwitterSearch(array(
				'campaign_id' => $this->view->campaign->id,
				'should_analyse' => !!$this->view->campaign->analysis_quota,
				'status' => 1
			));
		}

		$this->validateData($editingSearch);

		if ($this->_request->isPost()) {
			$editingSearch->fromArray($this->_request->getParams());

			$errorMessages = array();
			if (!$this->_request->query) {
				$errorMessages[] = 'Please enter a search query';
			}

			if (!$this->_request->name) {
				$errorMessages[] = 'Please enter a name';
			}

			if ($errorMessages) {
				foreach ($errorMessages as $message) {
					$this->_helper->FlashMessenger(array('error' => $message));
				}
			} else {
				$success = true;
				try {
					if ($this->_request->backfill) {
						if ($this->view->user->twitterToken) {
							set_time_limit(600);
							$editingSearch->save();
							try {
								$counts = $editingSearch->backfillTweets($this->view->user->twitterToken, $this->_request->backfill);
								$this->_helper->FlashMessenger(array('info' => 'Backfilled ' . $counts->fetched . ' tweets'));
							} catch (Exception $ex) {
								$this->_helper->FlashMessenger(array('error' => 'Backfill error: ' . $ex->getMessage()));
							}
						} else {
							$this->_helper->FlashMessenger(array('error' => 'You must authorize with a Twitter account to backfill tweets'));
							//don't redirect, return to form
							$success = false;
						}
					}

					if ($success) {
						$editingSearch->save();
						$this->_helper->FlashMessenger(array('info' => 'Search saved'));
					}
				} catch (Exception $ex) {
					if ($ex->getCode() == 23000) {
						$this->_helper->FlashMessenger(array('error' => 'A search with the same name or query already exists in this campaign'));
					} else {
						$this->_helper->FlashMessenger(array('error' => $ex->getMessage()));
					}
					$success = false;
				}

				if ($success) {
					$this->_helper->redirector->gotoSimple('index');
				}
			}
		}
		
		$this->view->editingSearch = $editingSearch;
		$this->view->presetRegions = Model_Region::fetchAll();
		$this->view->title = 'Edit Twitter Search';
	}

	/**
	 * Deletes a twitter search
	 * @permission delete_twitter_search
	 */
	public function deleteAction()
	{
		$search = Model_TwitterSearch::fetchById($this->_request->id);
		$this->validateData($search);
		
		if ($this->_request->isPost()) {
			$search->delete();
			$this->_helper->FlashMessenger(array('info' => 'Search deleted'));
		}
		$this->_helper->redirector->gotoSimple('index');
	}
}

