<?php

class RegionController extends CampaignController
{

    protected static $publicActions = array();

	public function init()
    {
		parent::init();
		$this->view->titleIcon = Model_Region::ICON_TYPE;
	}

	/**
	 * Lists all regions
	 * @user-level user
	 */
	public function indexAction()
    {
        $this->view->title = 'Regions';
		$this->view->regions = Model_Region::fetchAll();
        $this->view->tableHeaders = self::generateTableHeaders();
		$this->view->tableMetrics = self::tableMetrics();
        $this->view->badgeData = Model_Region::badgesData();
	}

	/**
	 * Views a specific region
	 * @user-level user
	 */
	public function viewAction()
	{
		/** @var Model_Region $region */
		$region = Model_Region::fetchById($this->_request->id);
		$this->validateData($region);

        $compareData = array();
        foreach($region->presences as $presence){
            $compareData[$presence->id] = (object)array(
                'presence'=>$presence,
                'graphs'=>$this->graphs($presence)
            );
        }

		$this->view->metricOptions = self::graphMetrics();
		$this->view->tableMetrics = self::tableMetrics();
        $this->view->compareData = $compareData;
		$this->view->title = $region->display_name;
		$this->view->titleIcon = 'icon-th-list';
		$this->view->titleInfo = $region->regionInfo();
        $this->view->region = $region;
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
        $this->view->title = 'New Region';
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
     * Edits/creates a region
     * @user-level user
     */
    public function editAction()
    {
        if ($this->_request->action == 'edit') {
            $editingRegion = Model_Region::fetchById($this->_request->id);
            $this->view->showButtons = true;
        } else {
            $editingRegion = new Model_Region();
            $this->view->showButtons = false;
        }

        $this->validateData($editingRegion);

        if ($this->_request->isPost()) {
//			$oldTimeZone = $editingRegion->timezone;
            $editingRegion->fromArray($this->_request->getParams());

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
                    $editingRegion->save();

                    if($this->_request->p){
                        $editingRegion->assignPresences($this->_request->p);
                    }
                    $this->_helper->FlashMessenger(array('info' => 'Region saved'));
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


        $this->view->editingRegion = $editingRegion;
        $this->view->title = 'Edit Region';
        $this->view->titleIcon = 'icon-edit';
    }



    /**
     * lists all regions
     * @user-level user
     */
    public function editAllAction()
    {

        $this->view->title = 'Edit All';
        $this->view->regions = Model_Region::fetchAll();

        if ($this->_request->isPost()) {

            $result = $this->_request->getParams();

            $editingRegions = array();

            foreach($result as $k => $v){
                if(preg_match('|^([0-9]+)\_(.+)$|', $k, $matches)){
                    if(!array_key_exists($matches[1], $editingRegions)) $editingRegions[$matches[1]] = array('id' => $matches[1]);
                    $editingRegions[$matches[1]][$matches[2]] = $v;
                }
            }

            $errorMessages = array();

            $editedRegions = array();

            foreach($editingRegions as $g){
                $editingRegion = Model_Region::fetchById($g['id']);
                $display_name = $editingRegion->display_name;
                $editingRegion->fromArray($g);

                if (!$g['display_name']) {
                    $errorMessages[] = 'Please enter a display name for '. $display_name;
                }

                $editedRegions[] = $editingRegion;

            }

            if ($errorMessages) {
                foreach ($errorMessages as $message) {
                    $this->_helper->FlashMessenger(array('error' => $message));
                }
            } else {
                try {
                    foreach($editedRegions as $region){
                        $region->save();
                    }

                    $this->_helper->FlashMessenger(array('info' => count($editedRegions) . ' SBUs saved'));
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
	 * Manages the presences that belong to a region
	 * @user-level manager
	 */
	public function manageAction()
    {
        /** @var Model_Region $region */
        $region = Model_Region::fetchById($this->_request->id);
        $this->validateData($region);

        if ($this->_request->isPost()) {
            $presenceIds = array();
            foreach ($this->_request->assigned as $ids) {
                foreach ($ids as $id) {
                    $presenceIds[] = $id;
                }
            }
            $region->assignPresences($presenceIds);
            $this->_helper->FlashMessenger(array('info' => 'SBU presences updated'));
            $this->_helper->redirector->gotoSimple('index');
        }

        $this->view->title = 'Manage Region Presences';
        $this->view->titleIcon = 'icon-tasks';
        $this->view->region = $region;
        $this->view->twitterPresences = Model_Presence::fetchAllTwitter();
        $this->view->facebookPresences = Model_Presence::fetchAllFacebook();
	}

	/**
	 * Deletes a region
	 * @user-level manager
	 */
	public function deleteAction()
    {
		$region = Model_Region::fetchById($this->_request->id);
		$this->validateData($region);

		if ($this->_request->isPost()) {
			$region->delete();
			$this->_helper->FlashMessenger(array('info' => 'SBU deleted'));
		}
		$this->_helper->redirector->gotoSimple('index');
	}

    /**
     * Gets all of the graph data for the requested presence
     */
    public function badgeDataAction()
    {
        Zend_Session::writeClose(); // release session on long running actions

	    /** @var Model_Region $region */
        $region = Model_Region::fetchById($this->_request->id);

        $response = $region->badges();

        $this->apiSuccess($response);

    }

	public function downloadAction() {
		parent::downloadAsCsv('region_index', Model_Region::badgesData(), Model_Region::fetchAll(), self::tableIndexHeaders());
	}


    public static function tableIndexHeaders()
    {
        $return = array(
            'name' => true,
            'total-rank' => true,
            'total-score' => true,
            'target-audience' => true
        );

        foreach(self::tableMetrics() as $name => $title){
            $return[$name] = true;
        }
        $return['countries'] = true;
        $return['options'] = false;

        return $return;
    }
}
