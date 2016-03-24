<?php

use Outlandish\SocialMonitor\Exception\SocialMonitorException;
use Outlandish\SocialMonitor\PresenceType\PresenceType;
use Outlandish\SocialMonitor\Report\ReportablePresence;
use Outlandish\SocialMonitor\Report\ReportGenerator;
use Outlandish\SocialMonitor\TableIndex\Header\Handle;

class PresenceController extends GraphingController
{
	protected static $publicActions = array('update-kpi-cache', 'report');

	protected function chartOptions(Model_Presence $presence = null) {
		if($presence) {
			return $presence->chartOptions();
		} else {
			return array(
				Chart_Compare::getInstance(),
				Chart_Popularity::getInstance(),
				Chart_PopularityTrend::getInstance(),
				Chart_ActionsPerDay::getInstance(),
				Chart_ResponseTime::getInstance()
			);
		}
	}

	/**
	 * Lists all presences
	 * @user-level user
	 */
	public function indexAction()
	{
		$objectCacheManager = $this->getContainer()->get('object-cache-manager');
		$table = $objectCacheManager->getPresencesTable();

		$rows = $objectCacheManager->getPresenceIndexRows($this->_request->getParam('force'));

        $this->view->pageTitle = $this->translator->trans('Global.presences');
        $this->view->presences = $table->getTableData();
        $this->view->rows = $rows;
        $this->view->tableHeaders = $table->getHeaders();
        $this->view->sortCol = Handle::getName();
		$this->view->regions = Model_Region::fetchAll();
	}

	/**
	 * Lists all presences
	 * @user-level user
	 */
	public function assignAction()
	{
		$presence = Model_PresenceFactory::getPresenceById($this->_request->getParam('id'));
		$this->validateData($presence);

		/** @var Model_User $user */
		$user = $this->view->user;

		$accessToken = $user->getAccessToken($presence->getType());

		if ($accessToken) {
			$presence->user = $user;
			$presence->save();
			$message = $this->translator->trans('Success.presence-assigned-user');
		} else {
			$message = $this->translator->trans('Error.presence-not-assigned-user');
		}
		$this->flashMessage($message);

		$this->_helper->redirector->gotoRoute(array('controller'=>'presence', 'action'=>'view', 'id'=>$presence->id));
	}

	/**
	 * Views a specific presence
	 * @user-level user
	 */
	public function viewAction()
	{
		$presence = Model_PresenceFactory::getPresenceById($this->_request->getParam('id'));
		$this->validateData($presence);

		$this->view->presence = $presence;
		$this->view->badgePartial = $this->badgeDetails($presence);
		$this->view->chartOptions = $this->chartOptions($presence);
        $allPresences = array();
        foreach (Model_PresenceFactory::getPresences() as $p) {
            $group = $p->getType()->getTitle();
            if (!isset($allPresences[$group])) {
                $allPresences[$group] = array();
            }
            $allPresences[$group][] = $p;
        }
        $this->view->allPresences = $allPresences;
	}

	public function downloadReportAction()
	{
        $presence = Model_PresenceFactory::getPresenceById($this->_request->getParam('id'));
        $this->validateData($presence);

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

        $url = $downloader->getUrl(new ReportablePresence($presence), $from, $to);

        do {
            $content = file_get_contents($url);
        } while(empty($content));

		header('Content-type: application/pdf');
		header('Content-Disposition: attachment; filename=report.pdf');
		echo $content;
		exit;
	}

    public function reportAction()
    {
        $presence = Model_PresenceFactory::getPresenceById($this->_request->getParam('id'));
        $this->validateData($presence);

        $presence->getTargetAudience();

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

        $report = (new ReportGenerator())->generate(new ReportablePresence($presence), $from, $to);
        $report->generate();
        $this->view->report = $report;
        $this->view->presence = $presence;
        $this->view->owner = $presence->getOwner();
        $this->_helper->layout()->setLayout('report');

    }

	/**
	 * Compares multiple presences
	 * @user-level user
	 */
	public function compareAction()
    {
        $compareData = array();
        foreach(explode(',',$this->_request->getParam('id')) as $id){
            $presence = Model_PresenceFactory::getPresenceById($id);
            $this->validateData($presence);
            $compareData[$id] = (object)array(
	            'presence'=>$presence,
	            'graphs'=>$this->graphs($presence)
            );
        }

        $this->view->pageTitle = $this->translator->trans('Presence.compare.page-title', ['%count%' => count($compareData)]);
	    $this->view->chartOptions = $this->chartOptions();
	    $this->view->tableMetrics = self::tableMetrics();
        $this->view->compareData = $compareData;
    }

	/**
	 * Creates a new presence
	 * @user-level manager
	 */
	public function newAction()
	{
		// do exactly the same as in editAction, but with a different title
		$this->editAction();
        $this->view->editType = true;
		$this->view->pageTitle = $this->translator->trans('Presence.new.page-title');
		$this->_helper->viewRenderer->setScriptAction('edit');
	}

	/**
	 * Edits/creates a presence
	 * @user-level user
	 */
	public function editAction()
	{
        Model_PresenceFactory::setDatabase(Zend_Registry::get('db')->getConnection());

		if ($this->_request->getActionName() == 'edit') {
            $presence = Model_PresenceFactory::getPresenceById($this->_request->getParam('id'));
            $this->view->showButtons = true;
			$this->view->isNew = false;
		} else {
			$presence = (object)array(
                'id' => null,
                'type' => null,
                'handle' => null,
                'sign_off' => null,
                'branding' => null
            );
            $this->view->showButtons = false;
			$this->view->isNew = true;
		}

		$this->validateData($presence);

		if ($this->_request->isPost()) {

			$errorMessages = array();
            $type = $this->_request->getParam('type');
            $handle = $this->_request->getParam('handle');
            $signOff = $this->_request->getParam('sign_off');
            $branding = $this->_request->getParam('branding');
            $size = $this->_request->getParam('size');
			$userId = $this->_request->getParam('user_id');
			if (!$type) {
				$errorMessages[] = $this->translator->trans('Error.missing-presence-type'); //'Please choose a type';
			}
			if (!$handle) {
				$errorMessages[] = $this->translator->trans('Error.missing-presence-handle'); //'Please enter a handle';
			}

            if (!$presence->id) {
                // can't change the type if editing
                $presence->type = $type;
            }
            $presence->handle = $handle;
            $presence->sign_off = $signOff;
            $presence->branding = $branding;

            if (!$errorMessages) {
                try {
                    if (!$presence->id) {
                        $type = PresenceType::get($type);
                        $presence = Model_PresenceFactory::createNewPresence($type, $handle, $signOff, $branding);
                        $presence->setSize($size);
						if ($presence->getType()->getRequiresAccessToken()) {
							$presence->user = $this->view->user;
						}
						$presence->testUpdate();
                        $presence->save();
                    } else {
                    	$presence->setSize($size);
						if ($presence->getType()->getRequiresAccessToken() && $userId) {
							$user = Model_User::fetchById($userId);
							$presence->setUser($user);
						}
                        $presence->update();
                        $presence->save();
                    }
                } catch (SocialMonitorException $ex) {
					$errorMessages[] = $ex->getMessage();
				} catch (Exception $ex) {
                    if (strpos($ex->getMessage(), '23000') !== false) {
                        $errorMessages[] = $this->translator->trans('Error.presence-exists'); //'Presence already exists';
                    } else {
                        $errorMessages[] = $ex->getMessage();
                    }
                }
            }

			if ($errorMessages) {
				foreach ($errorMessages as $message) {
                    $this->flashMessage($message, 'error');
				}
			} else {
				$objectCacheManager = $this->getContainer()->get('object-cache-manager');
				$table = $objectCacheManager->getPresencesTable();
				$objectCacheManager->invalidateObjectCache($table->getIndexName());

				$this->flashMessage($this->translator->trans('Success.presence-saved')); //'Presence saved');

				//if new presence created, update presence index cache so that it will appear in the presence index page
				if ($this->view->isNew) {
					//this takes too long when adding presences
					//$this->updatePresenceIndexCache();
				}

				$this->_helper->redirector->gotoRoute(array('controller'=>'presence', 'action'=>'view', 'id'=>$presence->id));
			}
		}

        $this->view->editType = false;
		$this->view->types = PresenceType::getAll();
		$this->view->countries = Model_Country::fetchAll();
        $this->view->groups = Model_Group::fetchAll();
		$this->view->presence = $presence;
		$this->view->pageTitle = $this->translator->trans('User.edit.page-title');//'Edit Presence';
	}

	/**
	 * Deletes a presence
	 * @user-level manager
	 */
	public function deleteAction()
	{
		$presence = Model_PresenceFactory::getPresenceById($this->_request->getParam('id'));
		$this->validateData($presence);

		if ($this->_request->isPost()) {
			$presence->delete();
            $this->flashMessage($this->translator->trans('Success.presence-deleted')); //'Presence deleted');
            $this->_helper->redirector->gotoSimple('index');
		} else {
            $this->flashMessage($this->translator->trans('Error.presence-deleted'));
            $this->_helper->redirector->gotoRoute(array('action'=>'view'));
        }
	}

	/**
	 * Gets all of the graph data for the requested presence
	 */
	public function graphDataAction() {
		Zend_Session::writeClose(); //release session on long running actions

		$this->validateChartRequest();

		$presence = Model_PresenceFactory::getPresenceById($this->_request->getParam('id'));
		if(!$presence) {
			$this->apiError($this->translator->trans('Error.presence-not-found')); //'Presence could not be found');
		}

		$dateRange = $this->getRequestDateRange();
		$start = $dateRange[0];
		$end = $dateRange[1];

		$chartObject = Chart_Factory::getChart($this->_request->getParam('chart'));

		$this->apiSuccess($chartObject->getChart($presence, $start, $end));
	}

	/**
	 * AJAX function for toggling whether a facebook status needs a response
	 */
	public function toggleResponseNeededAction() {
		$id = $this->_request->getParam('id');
		if (!$id) {
			$this->apiError($this->translator->trans('Error.missing-id'));//'Missing ID');
		}
		$stmt = $this->db()->prepare('UPDATE facebook_stream set needs_response = !needs_response WHERE id = :id');
		$stmt->execute(array(':id'=>$id));
		$changed = $stmt->rowCount();
		$this->apiSuccess(array('updated'=>$changed));
	}

	/**
	 * This should be called via a cron job (~hourly), and does not output anything
	 */
	public function updateKpiCacheAction() {
        //moved to be part of build-badge-data
        exit;
	}

    public function downloadAction() {
		$table = $this->getContainer()->get('table.presence-index');
        $csvData = Util_Csv::generateCsvData($table);
        Util_Csv::outputCsv($csvData, 'presences');
	    exit;
    }

}
