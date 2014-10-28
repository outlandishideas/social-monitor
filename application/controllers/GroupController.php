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
		$this->view->groups = Model_Group::fetchAll();
        $this->view->tableHeaders = $this->tableIndexHeaders();
        $this->view->sortCol = Header_Name::getName();
	}

	/**
	 * Views a specific group
	 * @user-level user
	 */
	public function viewAction()
	{
		/** @var Model_Group $group */
		$group = Model_Group::fetchById($this->_request->getParam('id'));
		$this->validateData($group);

        $this->view->badgePartial = $this->badgeDetails($group->getBadges());

		$this->view->chartOptions = self::chartOptions();
		$this->view->tableMetrics = self::tableMetrics();
        $this->view->group = $group;
        $this->view->title = 'SBU: ' . $group->display_name;
        $this->view->allCampaigns = Model_Group::fetchAll();
	}

    /**
     * Gets all of the graph data for the requested presence
     */
    public function graphDataAction() {
        Zend_Session::writeClose(); //release session on long running actions

        $this->validateChartRequest();

        /** @var $group NewModel_Presence */
        $group = Model_Group::fetchById($this->_request->getParam('id'));
        if(!$group) {
            $this->apiError('Group could not be found');
        }

        $dateRange = $this->getRequestDateRange();
        $start = $dateRange[0];
        $end = $dateRange[1];

        $chartObject = Chart_Factory::getChart($this->_request->getParam('chart'));

        $this->apiSuccess($chartObject->getChart($group, $start, $end));
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
        $presenceIds = $this->_request->getParam('presences');
        if($presenceIds){
	        $presenceIds = explode(',',html_entity_decode($presenceIds));
            foreach($presenceIds as $id){
                $presences[$id] = NewModel_PresenceFactory::getPresenceById($id);
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
        if ($this->_request->getActionName() == 'edit') {
            $editingGroup = Model_Group::fetchById($this->_request->getParam('id'));
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
            if (!$this->_request->getParam('display_name')) {
                $errorMessages[] = 'Please enter a display name';
            }

            if ($errorMessages) {
                foreach ($errorMessages as $message) {
                    $this->flashMessage($message, 'error');
                }
            } else {
                try {
                    $editingGroup->save();

                    $p = $this->_request->getParam('p');
                    if($p){
                        $editingGroup->assignPresences($p);
                    }
                    $this->flashMessage('SBU saved');
                    $this->_helper->redirector->gotoSimple('index');
                } catch (Exception $ex) {
                    if (strpos($ex->getMessage(), '23000') !== false) {
                        $this->flashMessage('Display name already taken', 'error');
                    } else {
                        $this->flashMessage($ex->getMessage(), 'error');
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
                    $this->flashMessage($message, 'error');
                }
            } else {
                try {
                    foreach($editedGroups as $group){
                        $group->save();
                    }

                    $this->flashMessage(count($editedGroups) . ' SBUs saved');
                    $this->_helper->redirector->gotoSimple('index');

                } catch (Exception $ex) {
                    if (strpos($ex->getMessage(), '23000') !== false) {
                        $this->flashMessage('Display name already taken', 'error');
                    } else {
                        $this->flashMessage($ex->getMessage(), 'error');
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
        $group = Model_Group::fetchById($this->_request->getParam('id'));
        $this->validateData($group);

        if ($this->_request->isPost()) {
            $presenceIds = array();
            foreach ($this->_request->getParam('assigned') as $ids) {
                foreach ($ids as $id) {
                    $presenceIds[] = $id;
                }
            }
            $group->assignPresences($presenceIds);
            $this->flashMessage('SBU presences updated');
            $this->_helper->redirector->gotoSimple('index');
        }

        $this->view->title = 'Manage SBU Presences';
        $this->view->titleIcon = 'icon-tasks';
        $this->view->group = $group;
        $this->view->presences = $this->managePresencesList();
	}

	/**
	 * Deletes a group
	 * @user-level manager
	 */
	public function deleteAction() {
		$group = Model_Group::fetchById($this->_request->getParam('id'));
		$this->validateData($group);

		if ($this->_request->isPost()) {
			$group->delete();
            $this->flashMessage('SBU deleted');
    		$this->_helper->redirector->gotoSimple('index');
        } else {
            $this->flashMessage('Incorrect usage of delete');
            $this->_helper->redirector->gotoRoute(array('action'=>'view'));
		}
	}

	public function downloadAction() {
        $csvData = Util_Csv::generateCsvData(Model_Group::fetchAll(), $this->tableIndexHeaders());
        Util_Csv::outputCsv($csvData, 'SBUs');
        exit;
	}

    protected function tableIndexHeaders()
    {
        return array(
            Header_Name::getInstance(),
            Header_TotalRank::getInstance(),
            Header_TotalScore::getInstance(),
            Header_TargetAudience::getInstance(),
            Header_Presences::getInstance(),
            Header_PresenceCount::getInstance(),
            Header_Options::getInstance()
        );
    }


}
