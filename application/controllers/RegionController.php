<?php

use Outlandish\SocialMonitor\Report\ReportableRegion;
use Outlandish\SocialMonitor\Report\ReportGenerator;
use Outlandish\SocialMonitor\TableIndex\Header\Name;

class RegionController extends CampaignController
{

    protected static $publicActions = array('report');

    protected function chartOptions() {
		$container = $this->getContainer();
		return array(
			$container->get('chart.compare'),
			$container->get('chart.reach'),
			$container->get('chart.engagement'),
			$container->get('chart.quality')
		);
    }

    protected function tableMetrics(){
        return array(
            Metric_PopularityTime::NAME => 'Time to Target Audience',
            Metric_ActionsPerDay::NAME => 'Actions Per Day',
            Metric_ResponseTime::NAME => 'Response Time',
        );
    }

	/**
	 * Lists all regions
	 * @user-level user
	 */
	public function indexAction()
    {
        /** @var \Outlandish\SocialMonitor\Cache\ObjectCacheManager $objectCacheManager */
        $objectCacheManager = $this->getContainer()->get('object-cache-manager');
        $table = $objectCacheManager->getRegionsTable();

        $rows = $objectCacheManager->getRegionIndexRows($this->_request->getParam('force'));

		$this->view->regions = $table->getTableData();
		$this->view->rows = $rows;
        $this->view->tableHeaders = $table->getHeaders();
        $this->view->sortCol = Name::NAME;
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

		$this->view->titleIcon = Model_Region::ICON_TYPE;
        $this->view->badgePartial = $this->badgeDetails($region);
        $this->view->chartOptions = $this->chartOptions();
        $this->view->region = $region;
        $this->updatePageTitle(['region' => $region->display_name]);
        $this->view->allCampaigns = Model_Region::fetchAll();
    }

    public function downloadReportAction()
    {
        /** @var Model_Region $region */
        $region = Model_Region::fetchById($this->_request->getParam('id'));
        $this->validateData($region);

        //if we don't have a now parameter create a DateTime now
        //else create a date from the now parameter
        $to = date_create_from_format("Y-m-d", $this->_request->getParam('to'));
        if (!$to) {
            $to = new DateTime();
        }

        //if we don't have a then parameter generate a default then from $now
        //else create a date from the then parameter
        $from = date_create_from_format("Y-m-d", $this->_request->getParam('from'));
        if(!$from) {
            $from = clone $to;
            $from->modify('-30 days');
        }

        //if $now is earlier than $then then reverse them.
        if ($to->getTimestamp() <= $from->getTimestamp()) {
            $oldThen = clone $from;
            $from = clone $to;
            $to = clone $oldThen;
        }

        $downloader = $this->getContainer()->get('report.downloader');

        $url = $downloader->getUrl(new ReportableRegion($region, $this->translator), $from, $to);

        do {
            $content = @file_get_contents($url);
        } while(empty($content));

        header('Content-type: application/pdf');
        header('Content-Disposition: attachment; filename=report.pdf');
        echo $content;
        exit;
    }

    public function reportAction()
    {
        /** @var Model_Region $region */
        $region = Model_Region::fetchById($this->_request->getParam('id'));
        $this->validateData($region);

        //if we don't have a now parameter create a DateTime now
        //else create a date from the now parameter
        $to = date_create_from_format("Y-m-d", $this->_request->getParam('to'));
        if (!$to) {
            $to = new DateTime();
        }

        //if we don't have a then parameter generate a default then from $now
        //else create a date from the then parameter
        $from = date_create_from_format("Y-m-d", $this->_request->getParam('from'));
        if(!$from) {
            $from = clone $to;
            $from->modify('-30 days');
        }

        //if $now is earlier than $then then reverse them.
        if ($to->getTimestamp() <= $from->getTimestamp()) {
            $oldThen = clone $from;
            $from = clone $to;
            $to = clone $oldThen;
        }

        $report = (new ReportGenerator())->generate(new ReportableRegion($region, $this->translator), $from, $to);
        $report->generate();
        $this->view->report = $report;
        $this->view->region = $region;
        $this->view->countries = $region->getCountries();
        $this->_helper->layout()->setLayout('report');

    }

	/**
	 * Creates a new SBU
	 * @user-level manager
	 */
	public function newAction()
    {
        // do exactly the same as in editAction, but with a different title
        $this->editAction();

		$presences = array();
        $presenceIds = $this->_request->getParam('presences');
        if($presenceIds){
	        $presenceIds = explode(',',html_entity_decode($presenceIds));
            foreach($presenceIds as $id){
                $presences[$id] = Model_PresenceFactory::getPresenceById($id);
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
            $this->view->isNew = false;
        } else {
            $editingRegion = new Model_Region();
            $this->view->isNew = true;
        }

        $this->validateData($editingRegion);

        if ($this->_request->isPost()) {
            $editingRegion->fromArray($this->_request->getParams());

            $errorMessages = array();
            if (!$this->_request->getParam('display_name')) {
                $errorMessages[] = $this->translator->trans('Error.display-name-missing');
            }

            if ($errorMessages) {
                foreach ($errorMessages as $message) {
                    $this->flashMessage($message, 'error');
                }
            } else {
                try {
                    $editingRegion->save();

					$this->invalidateTableCache();

					$this->flashMessage($this->translator->trans('route.region.edit.message.success'));
                    $this->_helper->redirector->gotoRoute(array('action' => 'view', 'id' => $editingRegion->id));
                } catch (Exception $ex) {
                    if (strpos($ex->getMessage(), '23000') !== false) {
                        $this->flashMessage($this->translator->trans('Error.display-name-exists'), 'error');
                    } else {
                        $this->flashMessage($ex->getMessage(), 'error');
                    }
                }
            }
        }


        $this->view->editingRegion = $editingRegion;
    }



    /**
     * lists all regions
     * @user-level user
     */
    public function editAllAction()
    {
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
                    $errorMessages[] = $this->translator->trans('route.region.edit-all.message.display-name-missing', ['%region%' => $display_name]);
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

					$this->invalidateTableCache();

                    $this->flashMessage($this->translator->trans('route.region.edit-all.message.success', ['%count%' => count($editedRegions)]));
                    $this->_helper->redirector->gotoSimple('index');

                } catch (Exception $ex) {
                    if (strpos($ex->getMessage(), '23000') !== false) {
						$this->flashMessage($this->translator->trans('Error.display-name-exists'), 'error');
                    } else {
                        $this->flashMessage($ex->getMessage(), 'error');
                    }
                }
            }

        }
    }

	/**
	 * Manages the countries that belong to a region
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

			$this->invalidateTableCache();

            $this->flashMessage($this->translator->trans('route.region.manage.message.success'));
            $this->_helper->redirector->gotoRoute(array('action'=>'view'));
        }

        $this->updatePageTitle(['region' => $region->display_name]);
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

			$this->invalidateTableCache();

            $this->flashMessage($this->translator->trans('route.region.delete.message.success'));
    		$this->_helper->redirector->gotoSimple('index');
        } else {
            $this->flashMessage($this->translator->trans('Error.invalid-delete'));
            $this->_helper->redirector->gotoRoute(array('action'=>'view'));
		}
	}

	public function downloadAction() {
        $table = $this->getContainer()->get('table.region-index');
        $csvData = Util_Csv::generateCsvData($table);
        Util_Csv::outputCsv($csvData, 'regions');
        exit;
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
            $this->apiError($this->translator->trans('route.region.graph-data.message.not-found'));
        }

        $dateRange = $this->getRequestDateRange();
        $start = $dateRange[0];
        $end = $dateRange[1];

		$chartName = $this->_request->getParam('chart');
		$chartObject = $this->getContainer()->get('chart.' . $chartName);

        $this->apiSuccess($chartObject->getChart($region, $start, $end));
    }

	protected function invalidateTableCache()
	{
		$objectCacheManager = $this->getContainer()->get('object-cache-manager');
		$table = $objectCacheManager->getRegionsTable();
		$objectCacheManager->invalidateObjectCache($table->getIndexName());
	}
}
