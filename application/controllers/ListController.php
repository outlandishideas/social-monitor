<?php

class ListController extends SocialApiController
{
	/**
	 * Displays a list of all twitter lists and their available stats and actions
	 * @permission list_twitter_list
	 */
	public function indexAction() {
		$this->view->title = 'Twitter Lists';
		$this->view->lists = $this->view->campaign->twitterLists;
		usort($this->view->lists, function($a, $b) {
			return strcasecmp(ltrim($a->name, '#@ '), ltrim($b->name, '@# '));
		});
	}

	/**
	 * Views a single twitter list
	 * @permission view_twitter_list
	 */
	public function viewAction()
	{
		$list = Model_TwitterList::fetchById($this->_request->id);
		$this->validateData($list);

		$this->view->title = 'Twitter List: ' . $list->name;
		$this->view->list = $list;
		$this->view->defaultLineId = self::makeLineId('Model_TwitterList', $list->id);
	}

	/**
	 * Compares activity from multiple twitter lists
	 * @permission compare_twitter_list
	 */
	public function compareAction() {
		$this->view->title = 'Compare Lists';
		$this->view->lists = $this->view->campaign->twitterLists;
	}

	/**
	 * Creates a new twitter list
	 * @permission create_twitter_list
	 */
	public function newAction()
	{
		// do exactly the same as in editAction, but with a different title
		$this->editAction();
		$this->view->title = 'New Twitter List';
		$this->_helper->viewRenderer->setScriptAction('edit');
	}

	/**
	 * Edits an existing twitter list
	 * @permission edit_twitter_list
	 */
	public function editAction()
	{
		if ($this->_request->action == 'edit') {
			$editingList = Model_TwitterList::fetchById($this->_request->id);
		} else {
			$editingList = new Model_TwitterList(array(
				'campaign_id' => $this->view->campaign->id,
				'should_analyse' => !!$this->view->campaign->analysis_quota,
				'status' => 1
			));
		}

		$this->validateData($editingList);

		if ($this->_request->isPost()) {
			$editingList->fromArray($this->_request->getParams());

			$errorMessages = array();
			if (!$this->_request->name) {
				$errorMessages[] = 'Please enter a list name';
			}
			if (!$this->_request->slug) {
				$errorMessages[] = 'Please enter a slug';
			}
			
			if ($errorMessages) {
				foreach ($errorMessages as $message) {
					$this->_helper->FlashMessenger(array('error' => $message));
				}
			} else {
				$success = true;
				try {
					if ($this->_request->backfill) {
						if($this->view->user->twitterToken) {
							set_time_limit(600);
							$editingList->save(); //save list now so it gets an ID to back fill with
							try {
								$counts = $editingList->backfillTweets($this->view->user->twitterToken, $this->_request->backfill);
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
						$editingList->save();
						$this->_helper->FlashMessenger(array('info' => 'List saved'));
					}
				} catch (Exception $ex) {
					if ($ex->getCode() == 23000) {
						$this->_helper->FlashMessenger(array('error' => 'A list with the same name or slug already exists in this campaign'));
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
		
		$this->view->editingList = $editingList;
		$this->view->title = 'Edit Twitter List';
	}

	/**
	 * Deletes a twitter list
	 * @permission delete_twitter_list
	 */
	public function deleteAction()
	{
		$list = Model_TwitterList::fetchById($this->_request->id);
		$this->validateData($list);
		
		if ($this->_request->isPost()) {
			$list->delete();
			$this->_helper->FlashMessenger(array('info' => 'List deleted'));
		}
		$this->_helper->redirector->gotoSimple('index');
	}

}

