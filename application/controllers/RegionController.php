<?php

class RegionController extends CampaignController
{

    protected static $publicActions = array();

    protected static function tableMetrics(){
        return array(
            Model_Presence::METRIC_POPULARITY_TIME => 'Time to Target Audience',
            Model_Presence::METRIC_POSTS_PER_DAY => 'Actions Per Day',
            Model_Presence::METRIC_RESPONSE_TIME => 'Response Time',
        );
    }

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
        $this->view->tableHeaders = $this->tableIndexHeaders();
        $this->view->sortCol = Header_Name::getName();
	}

    /**
     * Views a specific country
     * @user-level user
     */
    public function viewAction()
    {
        /** @var Model_Region $region */
        $region = Model_Region::fetchById($this->_request->getParam('id'));
        $this->validateData($region);

        $this->view->badgePartial = $this->badgeDetails($region);
        $this->view->chartOptions = $this->chartOptions();
        $this->view->region = $region;
        $this->view->title = 'Region: ' . $region->display_name;
        $this->view->allCampaigns = Model_Region::fetchAll();
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
        $presenceIds = $this->_request->getParam('presences');
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
        if ($this->_request->getActionName() == 'edit') {
            $editingRegion = Model_Region::fetchById($this->_request->getParam('id'));
            $this->view->showButtons = true;
        } else {
            $editingRegion = new Model_Region();
            $this->view->showButtons = false;
        }

        $this->validateData($editingRegion);

        if ($this->_request->isPost()) {
            $editingRegion->fromArray($this->_request->getParams());

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
                    $editingRegion->save();
                    $this->flashMessage('Region saved');
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
                    $this->flashMessage($message, 'error');
                }
            } else {
                try {
                    foreach($editedRegions as $region){
                        $region->save();
                    }

                    $this->flashMessage(count($editedRegions) . ' Regions saved');
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
	 * Manages the presences that belong to a region
	 * @user-level manager
	 */
	public function manageAction()
    {
        /** @var Model_Region $region */
        $region = Model_Region::fetchById($this->_request->getParam('id'));
        $this->validateData($region);

        if ($this->_request->isPost()) {
            $countryIds = array();
            foreach ($this->_request->getParam('assigned') as $id) {
                $countryIds[] = $id;
            }
            $region->assignCountries($countryIds);
            $this->flashMessage('Region countries updated');
            $this->_helper->redirector->gotoRoute(array('action'=>'view'));
        }

        $this->view->title = 'Manage Region Countries';
        $this->view->titleIcon = 'icon-tasks';
        $this->view->region = $region;
        $this->view->allCountries = Model_Country::fetchAll();
	}

	/**
	 * Deletes a region
	 * @user-level manager
	 */
	public function deleteAction()
    {
		$region = Model_Region::fetchById($this->_request->getParam('id'));
		$this->validateData($region);

		if ($this->_request->isPost()) {
			$region->delete();
            $this->flashMessage('Region saved');
    		$this->_helper->redirector->gotoSimple('index');
        } else {
            $this->flashMessage('Incorrect usage of delete');
            $this->_helper->redirector->gotoRoute(array('action'=>'view'));
		}
	}

	public function downloadAction() {
        $csvData = Util_Csv::generateCsvData(Model_Region::fetchAll(), $this->tableIndexHeaders());
        Util_Csv::outputCsv($csvData, 'regions');
        exit;
	}


    protected function tableIndexHeaders()
    {
        return array(
            Header_Name::getInstance(),
            Header_TotalRank::getInstance(),
            Header_TotalScore::getInstance(),
            Header_TargetAudience::getInstance(),
            Header_PercentTargetAudience::getInstance(),
            Header_Countries::getInstance(),
            Header_CountryCount::getInstance(),
            Header_PresenceCount::getInstance(),
            Header_Options::getInstance(),
        );
    }

    /**
     * Gets all of the graph data for the requested region
     */
    public function graphDataAction() {
        Zend_Session::writeClose(); //release session on long running actions

        $this->validateChartRequest();

        /** @var $region Model_Region */
        $region = Model_Region::fetchById($this->_request->getParam('id'));
        if(!$region) {
            $this->apiError('Region could not be found');
        }

        $dateRange = $this->getRequestDateRange();
        $start = $dateRange[0];
        $end = $dateRange[1];

        $chartObject = Chart_Factory::getChart($this->_request->getParam('chart'));

        $this->apiSuccess($chartObject->getChart($region, $start, $end));
    }
}
