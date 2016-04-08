<?php

use Outlandish\SocialMonitor\Report\ReportableGroup;
use Outlandish\SocialMonitor\Report\ReportGenerator;
use Outlandish\SocialMonitor\TableIndex\Header\Name;

class GroupController extends CampaignController {

    protected static $publicActions = array('report');

    protected $formInputLabels = array(
        'display_name' => 'route.group.edit.label.display-name',
        'audience' => 'route.group.edit.label.audience-size'
    );
	/**
	 * @param bool $validate
	 * @return Model_Group
	 */
	protected function getRequestedGroup($validate = true)
	{
		$group = Model_Group::fetchById($this->_request->getParam('id'));
		if ($validate) {
			$this->validateData($group);
		}
		return $group;
	}

	/**
	 * Lists all groups
	 * @user-level user
	 */
	public function indexAction() {

        $objectCacheManager = $this->getContainer()->get('object-cache-manager');
        $table = $objectCacheManager->getGroupsTable();

        $rows = $objectCacheManager->getGroupIndexRows($this->_request->getParam('force'));

		$this->view->groups = $table->getTableData();
		$this->view->rows = $rows;
        $this->view->tableHeaders = $table->getHeaders();
        $this->view->sortCol = Name::NAME;
	}

	/**
	 * Views a specific group
	 * @user-level user
	 */
	public function viewAction()
	{
		$group = $this->getRequestedGroup();

		$this->view->titleIcon = Model_Group::ICON_TYPE;
        $this->view->badgePartial = $this->badgeDetails($group);
		$this->view->chartOptions = self::chartOptions();
		$this->view->tableMetrics = $this->tableMetrics();
        $this->view->group = $group;
        $this->updatePageTitle(['group' => $group->display_name]);
        $this->view->allCampaigns = Model_Group::fetchAll();
		$this->view->pdfLink = $this->getContainer()->get('kpi_download_linker')->link();
	}

    public function downloadReportAction()
    {
		$group = $this->getRequestedGroup();

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

        $url = $downloader->getUrl(new ReportableGroup($group, $this->translator), $from, $to);

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
		$group = $this->getRequestedGroup();

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

        $report = (new ReportGenerator())->generate(new ReportableGroup($group, $this->translator), $from, $to);
        $report->generate();
        $this->view->report = $report;
        $this->view->group = $group;
        $this->_helper->layout()->setLayout('report');

    }

    /**
     * Gets all of the graph data for the requested presence
     */
    public function graphDataAction() {
        Zend_Session::writeClose(); //release session on long running actions

        $this->validateChartRequest();

		$group = $this->getRequestedGroup(false);
        if(!$group) {
			$this->apiError($this->translator->trans('route.group.graph-data.message.not-found'));
        }

        $dateRange = $this->getRequestDateRange();
        $start = $dateRange[0];
        $end = $dateRange[1];

		$chartName = $this->_request->getParam('chart');
		$chartObject = $this->getContainer()->get('chart.' . $chartName);

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
     * Edits/creates a group
     * @user-level user
     */
    public function editAction()
    {
        if ($this->_request->getActionName() == 'edit') {
			$editingGroup = $this->getRequestedGroup();
            $this->view->isNew = false;
        } else {
            $editingGroup = new Model_Group();
            $this->view->isNew = true;
        }

        if ($this->_request->isPost()) {
//			$oldTimeZone = $editingGroup->timezone;

            if($this->setProperties($editingGroup)){
                try {
                    $editingGroup->save();

					$this->invalidateTableCache();

					$p = $this->_request->getParam('p');
                    if($p){
                        $editingGroup->assignPresences($p);
                    }
                    $this->flashMessage($this->translator->trans('route.group.edit.message.success'));
                    $this->_helper->redirector->gotoRoute(array('action' => 'view'));
                } catch (Exception $ex) {
                    if (strpos($ex->getMessage(), '23000') !== false) {
                        $this->flashMessage($this->translator->trans('Error.display-name-exists'), 'error');
                    } else {
                        $this->flashMessage($ex->getMessage(), 'error');
                    }
                }
            }
        }


        $this->view->editingGroup = $editingGroup;
    }



    /**
     * Edits/creates a country
     * @user-level user
     */
    public function editAllAction()
    {
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

            /** @var Model_Group[] $editedGroups */
            $editedGroups = array();

            foreach($editingGroups as $g){
                $editingGroup = Model_Group::fetchById($g['id']);
                $display_name = $editingGroup->display_name;
                $editingGroup->fromArray($g);

                if (!$g['display_name']) {
                    $errorMessages[] = $this->translator->trans('route.group.edit-all.message.display-name-missing', ['%group%' => $display_name]);
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

					$this->invalidateTableCache();

                    $this->flashMessage($this->translator->trans('route.group.edit-all.message.success', ['%count%' => count($editedGroups)]));
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
	 * Manages the presences that belong to a group
	 * @user-level manager
	 */
	public function manageAction() {
		$group = $this->getRequestedGroup();

        if ($this->_request->isPost()) {
            $presenceIds = array();
            foreach ($this->_request->getParam('assigned') as $ids) {
                foreach ($ids as $id) {
                    $presenceIds[] = $id;
                }
            }
            $group->assignPresences($presenceIds);

			$this->invalidateTableCache();

            $this->flashMessage($this->translator->trans('route.group.manage.message.success'));
            $this->_helper->redirector->gotoRoute(array('action'=>'view'));
        }

        $this->updatePageTitle(['group' => $group->display_name]);
        $this->view->group = $group;
        $this->view->presences = $this->managePresencesList();
	}

	/**
	 * Deletes a group
	 * @user-level manager
	 */
	public function deleteAction() {
		$group = $this->getRequestedGroup();

		if ($this->_request->isPost()) {
			$group->delete();

			$this->invalidateTableCache();

            $this->flashMessage($this->translator->trans('route.group.delete.message.success'));
    		$this->_helper->redirector->gotoSimple('index');
        } else {
            $this->flashMessage($this->translator->trans('Error.invalid-delete'));
            $this->_helper->redirector->gotoRoute(array('action'=>'view'));
		}
	}

	public function downloadAction() {
        $table = $this->getContainer()->get('table.group-index');
        $csvData = Util_Csv::generateCsvData($table);
        Util_Csv::outputCsv($csvData, 'SBUs');
        exit;
	}

	function getIndexTable($objectCacheManager)
	{
		return $objectCacheManager->getGroupsTable();
	}


}
