<?php

use Outlandish\SocialMonitor\Exception\SocialMonitorException;
use Outlandish\SocialMonitor\PresenceType\PresenceType;
use Outlandish\SocialMonitor\Report\ReportablePresence;
use Outlandish\SocialMonitor\Report\ReportGenerator;
use Outlandish\SocialMonitor\TableIndex\Header\Handle;

class PresenceController extends GraphingController
{
	protected static $publicActions = array('update-kpi-cache', 'report');

	/**
	 * @param bool $validate
	 * @return Model_Presence
	 */
	protected function getRequestedPresence($validate = true)
	{
		$presence = Model_PresenceFactory::getPresenceById($this->_request->getParam('id'));
		if ($validate) {
			$this->validateData($presence);
		}
		return $presence;
	}

	protected function chartOptions(Model_Presence $presence = null) {
		$options = array();
		if($presence) {
			$names = $presence->chartOptionNames();
		} else {
			$names = array(
				'chart.compare',
				'chart.popularity',
				'chart.popularity-trend',
				'chart.actionsPerDay',
				'chart.response-time'
			);
		}
		$container = $this->getContainer();
		foreach ($names as $chartName) {
			$options[] = $container->get($chartName);
		}
		return $options;
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

        $this->view->presences = $table->getTableData();
        $this->view->rows = $rows;
        $this->view->tableHeaders = $table->getHeaders();
        $this->view->sortCol = Handle::NAME;
	}

	/**
	 * Assigns an access token to the current user
	 * @user-level user
	 */
	public function assignAction()
	{
		$presence = $this->getRequestedPresence();

		/** @var Model_User $user */
		$user = $this->view->user;

		$accessToken = $user->getAccessToken($presence->getType());

		if ($accessToken) {
			$presence->user = $user;
			$presence->save();
			$message = $this->translator->trans('route.presence.assign.message.success');
		} else {
			$message = $this->translator->trans('route.presence.assign.message.failure');
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
		$presence = $this->getRequestedPresence();

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
		$this->updatePageTitle(['presence' => $presence->getName()]);
		$this->view->pdfLink = $this->getContainer()->get('kpi_download_linker')->link();
		$this->view->joyride = $this->getContainer()->get('joyride.presence');
	}

	/**
	 * Download a report for this presence
	 *
	 * @user-level user
	 */
	public function downloadReportAction()
	{
		$presence = $this->getRequestedPresence();

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

        $url = $downloader->getUrl(new ReportablePresence($presence, $this->translator), $from, $to);

        do {
            $content = @file_get_contents($url);
        } while(empty($content));

		header('Content-type: application/pdf');
		header('Content-Disposition: attachment; filename=report.pdf');
		echo $content;
		exit;
	}

	/**
	 * View the data for this presence in report format
	 *
	 * This is used by software to convert HTML into PDF
	 */
    public function reportAction()
    {
		$presence = $this->getRequestedPresence();

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

        $report = (new ReportGenerator())->generate(new ReportablePresence($presence, $this->translator), $from, $to);
        $report->generate();
        $this->view->report = $report;
        $this->view->presence = $presence;
        $this->view->owner = $presence->getOwner();
        $this->_helper->layout()->setLayout('report');

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
		$this->_helper->viewRenderer->setScriptAction('edit');
	}

	/**
	 * Edits/creates a presence
	 * @user-level manager
	 */
	public function editAction()
	{
		if ($this->_request->getActionName() == 'edit') {
			$presence = $this->getRequestedPresence();
			$this->view->isNew = false;
		} else {
			$presence = (object)array(
                'id' => null,
                'type' => null,
                'handle' => null,
                'sign_off' => null,
                'branding' => null
            );
			$this->view->isNew = true;
		}

		if ($this->_request->isPost()) {

			$errorMessages = array();
            $type = $this->_request->getParam('type');
            $handle = $this->_request->getParam('handle');
            $signOff = $this->_request->getParam('sign_off');
            $branding = $this->_request->getParam('branding');
            $size = $this->_request->getParam('size');
			$userId = $this->_request->getParam('user_id');
			if (!$type) {
				$errorMessages[] = $this->translator->trans('route.presence.edit.message.missing-type'); //'Please choose a type';
			}
			if (!$handle) {
				$errorMessages[] = $this->translator->trans('route.presence.edit.message.missing-handle'); //'Please enter a handle';
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
                        $presence = Model_PresenceFactory::createNewPresence($type, $handle, $signOff, $branding, $this->view->user, $size);
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
                        $errorMessages[] = $this->translator->trans('route.presence.edit.message.already-exists'); //'Presence already exists';
                    } else {
                        $errorMessages[] = htmlspecialchars($ex->getMessage());
                    }
                }
            }

			if ($errorMessages) {
				foreach ($errorMessages as $message) {
                    $this->flashMessage($message, 'error');
				}
			} else {
				$this->invalidateTableCache();

				$this->flashMessage($this->translator->trans('route.presence.edit.message.success')); //'Presence saved');

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
	}

	/**
	 * Deletes a presence
	 * @user-level manager
	 */
	public function deleteAction()
	{
		$presence = $this->getRequestedPresence();

		if ($this->_request->isPost()) {
			$presence->delete();

			$this->invalidateTableCache();

            $this->flashMessage($this->translator->trans('route.presence.delete.message.success')); //'Presence deleted');
            $this->_helper->redirector->gotoSimple('index');
		} else {
            $this->flashMessage($this->translator->trans('Error.invalid-delete'));
            $this->_helper->redirector->gotoRoute(array('action'=>'view'));
        }
	}

	/**
	 * Gets all of the graph data for the requested presence
	 *
	 * @user-level user
	 */
	public function graphDataAction() {
		Zend_Session::writeClose(); //release session on long running actions

		$this->validateChartRequest();

		$presence = $this->getRequestedPresence(false);
		if(!$presence) {
			$this->apiError($this->translator->trans('route.presence.graph-data.message.not-found')); //'Presence could not be found');
		}

		$dateRange = $this->getRequestDateRange();
		$start = $dateRange[0];
		$end = $dateRange[1];

		$chartName = $this->_request->getParam('chart');
		$chartObject = $this->getContainer()->get('chart.' . $chartName);

		$this->apiSuccess($chartObject->getChart($presence, $start, $end));
	}

	/**
	 * AJAX function for toggling whether a facebook status needs a response
	 *
	 * @user-level user
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

	/**
	 * Download a CSV of the index table data
	 *
	 * @user-level user
	 */
    public function downloadAction() {
		$table = $this->getContainer()->get('table.presence-index');
        $csvData = Util_Csv::generateCsvData($table);
        Util_Csv::outputCsv($csvData, 'presences');
	    exit;
    }

	protected function invalidateTableCache()
	{
		$objectCacheManager = $this->getContainer()->get('object-cache-manager');
		$table = $objectCacheManager->getPresencesTable();
		$objectCacheManager->invalidateObjectCache($table->getIndexName());
	}
}
