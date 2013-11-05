<?php

class GroupController extends CampaignController {

    protected static $publicActions = array();

	public function init() {
		parent::init();
		$this->view->titleIcon = Model_Group::ICON_TYPE;
	}

	/**
	 * Lists all groups
	 * @user-level user
	 */
	public function indexAction() {
        $this->view->title = 'SBUs';
		$this->view->groups = Model_Group::fetchAll();
        $this->view->tableHeaders = static::generateTableHeaders();
		$this->view->tableMetrics = self::tableMetrics();
        $this->view->badgeData = Model_Group::badgesData();
	}

	/**
	 * Views a specific group
	 * @user-level user
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
        $this->view->badges = Model_Badge::$ALL_BADGE_TYPES;
	}

	/**
	 * Creates a new SBU
	 * @user-level manager
	 */
	public function newAction()
    {
        // do exactly the same as in editAction, but with a different title
        $this->editAction();
        $this->view->title = 'New SBU';
	    $this->view->titleIcon = 'icon-plus-sign';

        $presences = array();
        $presenceIds = $this->_request->presences;
        if($presenceIds){
	        $presenceIds = explode(',',html_entity_decode($presenceIds));
            foreach($presenceIds as $id){
                $presences[$id] = Model_Presence::fetchById($id);
            }
        }

        $this->view->presences = $presences;
        $this->_helper->viewRenderer->setScriptAction('edit');
    }

    /**
     * Edits/creates a group
     * @user-level user
     */
    public function editAction()
    {
        if ($this->_request->action == 'edit') {
            $editingGroup = Model_Group::fetchById($this->_request->id);
            $this->view->showButtons = true;
        } else {
            $editingGroup = new Model_Group();
            $this->view->showButtons = false;
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

                    if($this->_request->p){
                        $editingGroup->assignPresences($this->_request->p);
                    }
                    $this->_helper->FlashMessenger(array('info' => 'SBU saved'));
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
        $this->view->title = 'Edit SBU';
        $this->view->titleIcon = 'icon-edit';
    }



    /**
     * Edits/creates a country
     * @user-level user
     */
    public function editAllAction()
    {

        $this->view->title = 'Edit All';
        $this->view->groups = Model_Group::fetchAll();

        if ($this->_request->isPost()) {

            $result = $this->_request->getParams();

            $editingGroups = array();

            foreach($result as $k => $v){
                if(preg_match('|^([0-9]+)\_(.+)$|', $k, $matches)){
                    if(!array_key_exists($matches[1], $editingGroups)) $editingGroups[$matches[1]] = array('id' => $matches[1]);
                    $editingGroups[$matches[1]][$matches[2]] = $v;
                }
            }

            $errorMessages = array();

            $editedCountries = array();

            foreach($editingGroups as $g){
                $editingGroup = Model_Group::fetchById($g['id']);
                $display_name = $editingGroup->display_name;
                $editingGroup->fromArray($g);

                if (!$g['display_name']) {
                    $errorMessages[] = 'Please enter a display name for '. $display_name;
                }

                $editedGroups[] = $editingGroup;

            }

            if ($errorMessages) {
                foreach ($errorMessages as $message) {
                    $this->_helper->FlashMessenger(array('error' => $message));
                }
            } else {
                try {
                    foreach($editedGroups as $group){
                        $group->save();
                    }

                    $this->_helper->FlashMessenger(array('info' => count($editedGroups) . ' SBUs saved'));
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
	 * Manages the presences that belong to a group
	 * @user-level manager
	 */
	public function manageAction() {
        /** @var Model_Group $group */
        $group = Model_Group::fetchById($this->_request->id);
        $this->validateData($group);

        if ($this->_request->isPost()) {
            $presenceIds = array();
            foreach ($this->_request->assigned as $ids) {
                foreach ($ids as $id) {
                    $presenceIds[] = $id;
                }
            }
            $group->assignPresences($presenceIds);
            $this->_helper->FlashMessenger(array('info' => 'SBU presences updated'));
            $this->_helper->redirector->gotoSimple('index');
        }

        $this->view->title = 'Manage SBU Presences';
        $this->view->titleIcon = 'icon-tasks';
        $this->view->group = $group;
        $this->view->twitterPresences = Model_Presence::fetchAllTwitter();
        $this->view->facebookPresences = Model_Presence::fetchAllFacebook();
	}

	/**
	 * Deletes a group
	 * @user-level manager
	 */
	public function deleteAction() {
		$group = Model_Group::fetchById($this->_request->id);
		$this->validateData($group);

		if ($this->_request->isPost()) {
			$group->delete();
			$this->_helper->FlashMessenger(array('info' => 'SBU deleted'));
		}
		$this->_helper->redirector->gotoSimple('index');
	}

    /**
     * Gets all of the graph data for the requested presence
     */
    public function badgeDataAction() {
        Zend_Session::writeClose(); // release session on long running actions

	    /** @var Model_Group $group */
        $group = Model_Group::fetchById($this->_request->id);

        $response = $group->badges();

        $this->apiSuccess($response);

    }

    public function getAllBadgeData(){
        return Model_Group::badgesData();
    }

    public function getAllCampaigns(){
        return Model_Group::fetchAll();
    }

}
