<?php

class FacebookController extends SocialApiController
{
	/**
	 * Lists all facebook pages in the current campaign
	 * @permission list_facebook_page
	 */
	public function indexAction() {

		$this->view->title = 'Facebook Pages';
		$this->view->pages = $this->view->campaign->facebookPages;
	}

	/**
	 * Views a specific facebook page
	 * @permission view_facebook_page
	 */
	public function viewAction()
	{
		$page = Model_FacebookPage::fetchById($this->_request->id);
		$this->validateData($page, 'page');

		$this->view->title = $page->name;
		$this->view->page = $page;
		$this->view->defaultLineId = self::makeLineId('Model_FacebookPage', $page->id);
	}

	/**
	 * Compares activity from multiple facebook pages
	 * @permission compare_facebook_page
	 */
	public function compareAction() {
		$this->view->title = 'Compare Pages';
		$this->view->pages = $this->view->campaign->facebookPages;
	}

	/**
	 * Creates a new facebook page
	 * @permission create_facebook_page
	 */
	public function newAction()
	{
		// do exactly the same as in editAction, but with a different title
		$this->editAction();
		$this->view->title = 'Track a Facebook Page';
		$this->_helper->viewRenderer->setScriptAction('edit');
	}

	/**
	 * Edits/creates a facebook page
	 * @permission edit_facebook_page
	 */
	public function editAction()
	{
		if ($this->_request->action == 'edit') {
			$editingPage = Model_FacebookPage::fetchById($this->_request->id);
		} else {
			$editingPage = new Model_FacebookPage();
			$editingPage->campaign_id = $this->view->campaign->id;
			$editingPage->should_analyse = $this->view->campaign->analysis_quota;
		}

		$this->validateData($editingPage, 'page');

		if ($this->_request->isPost()) {
			$editingPage->fromArray($this->_request->getParams());

			$errorMessages = array();
			if (empty($this->_request->username)) {
				$errorMessages[] = 'Please enter a username';
			}
			
			if (!$errorMessages) {
				try {
					$editingPage->updateInfo();
					$editingPage->save();

					$this->_helper->redirector->gotoSimple('index');
				} catch (Exception $ex) {
					$errorMessages[] = $ex->getMessage();
				}
			}
			
			if ($errorMessages) {
				foreach ($errorMessages as $message) {
					$this->_helper->FlashMessenger(array('error'=>$message));
				}
			} else {
				$this->_helper->redirector->gotoSimple('index');
			}
		}

		$this->view->editingPage = $editingPage;
		$this->view->title = 'Edit Facebook Page';
	}

	/**
	 * Updates the name, stats, pic etc for the given facebook page
	 * @permission update_facebook_page
	 */
	public function updateAction()
	{
		$page = Model_FacebookPage::fetchById($this->_request->id);
		$this->validateData($page, 'page');

		$page->updateInfo();
		$page->save();

		$this->_helper->FlashMessenger(array('info'=>'Updated page info from Facebook API'));
		$this->_helper->redirector->gotoSimple('index');
	}

	/**
	 * Deletes a facebook page
	 * @permission delete_facebook_page
	 */
	public function deleteAction()
	{
		$page = Model_FacebookPage::fetchById($this->_request->id);
		$this->validateData($page, 'page');
		
		if ($this->_request->isPost()) {
			$page->delete();
			$this->_helper->FlashMessenger(array('info' => 'Page deleted'));
		}
		$this->_helper->redirector->gotoSimple('index');
	}

}

