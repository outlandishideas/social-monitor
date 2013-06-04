<?php

class GroupController extends CampaignController {

	/**
	 * Lists all groups
	 * @permission list_group
	 */
	public function indexAction() {
        $this->view->title = 'SBUs';
        $this->view->titleIcon = Model_Group::ICON_TYPE;
		$this->view->groups = Model_Group::fetchAll();
		$this->view->tableMetrics = self::tableMetrics();
	}

	/**
	 * Views a specific group
	 * @permission view_group
	 */
	public function viewAction()
	{
		/** @var Model_Group $group */
		$group = Model_Group::fetchById($this->_request->id);
		$this->validateData($group);

        $compareData = array();
        foreach($group->presences as $presence){
            $compareData[$presence->id] = (object)array(
                'presence'=>$presence,
                'graphs'=>$this->graphs($presence)
            );
        }

		$this->view->metricOptions = self::graphMetrics();
		$this->view->tableMetrics = self::tableMetrics();
        $this->view->compareData = $compareData;
		$this->view->title = $group->display_name;
		$this->view->titleIcon = 'icon-th-list';
		$this->view->titleInfo = $group->groupInfo();
        $this->view->group = $group;
	}

    public function newAction()
    {
        // do exactly the same as in editAction, but with a different title
        $this->editAction();
        $this->view->title = 'New Group';
	    $this->view->titleIcon = 'icon-plus-sign';

        $presences = array();
        $ids = $this->_request->id;
        if($ids){
            $ids = explode(',',html_entity_decode($ids));
            if(!empty($ids)){
                foreach($ids as $id){
                    $presences[$id] = Model_Presence::fetchById($id);
                }
            }
        }

        $this->view->presences = $presences;
        $this->_helper->viewRenderer->setScriptAction('edit');
    }

    /**
     * Edits/creates a group
     * @permission edit_group
     */
    public function editAction()
    {
        if ($this->_request->action == 'edit') {
            $editingGroup = Model_Group::fetchById($this->_request->id);
            $this->showButtons = true;
        } else {
            $editingGroup = new Model_Group();
            $this->showButtons = false;
        }

        $this->validateData($editingGroup);

        if ($this->_request->isPost()) {
//			$oldTimeZone = $editingGroup->timezone;
            $editingGroup->fromArray($this->_request->getParams());

            $errorMessages = array();
            if (!$this->_request->display_name) {
                $errorMessages[] = 'Please enter a display name';
            }

            if ($errorMessages) {
                foreach ($errorMessages as $message) {
                    $this->_helper->FlashMessenger(array('error' => $message));
                }
            } else {
                try {
                    $editingGroup->save();

                    if($this->_request->presences){
                        $editingGroup->assignPresences($this->_request->presences);
                    }
                    $this->_helper->FlashMessenger(array('info' => 'Group saved'));
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


        $this->view->editingGroup = $editingGroup;
        $this->view->title = 'Edit Group';
        $this->view->titleIcon = 'icon-edit';
    }

	/**
	 * Manages the presences that belong to a group
	 * @permission manage_group
	 */
	public function manageAction() {
		/** @var Model_Group $group */
		$group = Model_Group::fetchById($this->_request->id);
		$this->validateData($group);

		if ($this->_request->isPost()) {
			$group->assignPresences($this->_request->presences);
			$this->_helper->FlashMessenger(array('info' => 'Group presences updated'));
			$this->_helper->redirector->gotoSimple('index');
		}

		$this->view->title = 'Manage Group Presences';
		$this->view->titleIcon = 'icon-tasks';
		$this->view->group = $group;
		$this->view->twitterPresences = Model_Presence::fetchAllTwitter();
		$this->view->facebookPresences = Model_Presence::fetchAllFacebook();
	}

	/**
	 * Deletes a group
	 * @permission delete_group
	 */
	public function deleteAction() {
		$group = Model_Group::fetchById($this->_request->id);
		$this->validateData($group);

		if ($this->_request->isPost()) {
			$group->delete();
			$this->_helper->FlashMessenger(array('info' => 'Group deleted'));
		}
		$this->_helper->redirector->gotoSimple('index');
	}
}
