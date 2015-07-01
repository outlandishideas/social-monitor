<?php

use mikehaertl\wkhtmlto\Pdf;
use Outlandish\SocialMonitor\Report\ReportablePresence;
use Outlandish\SocialMonitor\Report\ReportGenerator;
use Outlandish\SocialMonitor\TableIndex\TableIndex;

class PresenceController extends GraphingController
{
	protected static $publicActions = array('update-kpi-cache', 'report');

	protected function chartOptions() {
        return array(
            Chart_Compare::getInstance(),
            Chart_Popularity::getInstance(),
            Chart_PopularityTrend::getInstance(),
            Chart_ActionsPerDay::getInstance(),
            Chart_ResponseTime::getInstance()
        );
	}

	/**
	 * Lists all presences
	 * @user-level user
	 */
	public function indexAction()
	{
		Model_PresenceFactory::setDatabase(Zend_Registry::get('db')->getConnection());
		$presences = Model_PresenceFactory::getPresences();

        /** @var TableIndex $indexTable */
        $indexTable = $this->getContainer()->get('table.presence-index');
        $rows = $this->getTableIndex('presence-index', $indexTable, $presences);

        $this->view->title = 'Presences';
        $this->view->presences = $presences;
        $this->view->rows = $rows;
        $this->view->tableHeaders = $indexTable->getHeaders();
        $this->view->sortCol = Header_Handle::getName();
	}

	/**
	 * Views a specific presence
	 */
	public function viewAction()
	{
		$presence = Model_PresenceFactory::getPresenceById($this->_request->getParam('id'));
		$this->validateData($presence);

		$this->view->presence = $presence;
		$this->view->badgePartial = $this->badgeDetails($presence);
		$this->view->chartOptions = $this->chartOptions();
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

        $this->view->title = 'Comparing '.count($compareData).' Presences';
        $this->view->titleIcon = 'icon-exchange';
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
		$this->view->title = 'New presence';
		$this->view->titleIcon = 'icon-plus-sign';
		$this->view->isNew = true;
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
		} else {
			$presence = (object)array(
                'id' => null,
                'type' => null,
                'handle' => null,
                'sign_off' => null,
                'branding' => null
            );
            $this->view->showButtons = false;
		}

		$this->validateData($presence);

		if ($this->_request->isPost()) {

			$errorMessages = array();
            $type = $this->_request->getParam('type');
            $handle = $this->_request->getParam('handle');
            $signOff = $this->_request->getParam('sign_off');
            $branding = $this->_request->getParam('branding');
            $size = $this->_request->getParam('size');
			if (!$type) {
				$errorMessages[] = 'Please choose a type';
			}
			if (!$handle) {
				$errorMessages[] = 'Please enter a handle';
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
                        $type = Enum_PresenceType::get($type);
                        $presence = Model_PresenceFactory::createNewPresence($type, $handle, $signOff, $branding);
                        $presence->setSize($size);
                        $presence->save();
                    } else {
                    	$presence->setSize($size);
                        $presence->update();
                        $presence->save();
                    }
                } catch (Exception $ex) {
                    if (strpos($ex->getMessage(), '23000') !== false) {
                        $errorMessages[] = 'Presence already exists';
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
                $this->flashMessage('Presence saved');
				$this->_helper->redirector->gotoRoute(array('controller'=>'presence', 'action'=>'view', 'id'=>$presence->id));
			}
		}

        $this->view->editType = false;
		$this->view->types = Enum_PresenceType::enumValues();
		$this->view->countries = Model_Country::fetchAll();
        $this->view->groups = Model_Group::fetchAll();
		$this->view->presence = $presence;
		$this->view->title = 'Edit Presence';
		$this->view->titleIcon = 'icon-edit';
		$this->view->isNew = false;
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
            $this->flashMessage('Presence deleted');
            $this->_helper->redirector->gotoSimple('index');
		} else {
            $this->flashMessage('Incorrect usage of delete');
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
			$this->apiError('Presence could not be found');
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
			$this->apiError('Missing ID');
		}
		$stmt = $this->db()->prepare('UPDATE facebook_stream set needs_response = !needs_response WHERE id = :id');
		$stmt->execute(array(':id'=>$id));
		$changed = $stmt->rowCount();
		$this->apiSuccess(array('updated'=>$changed));
	}

	/**
	 * AJAX function for fetching the posts/tweets for a presence
	 */
	public function statusesAction() {
		Zend_Session::writeClose(); //release session on long running actions

		$dateRange = $this->getRequestDateRange();
		if (!$dateRange) {
			$this->apiError('Missing date range');
		}

		$presence = Model_PresenceFactory::getPresenceById($this->_request->getParam('id'));
		if (!$presence) {
			$this->apiError('Presence not found');
		}
        $format = $this->_request->getParam('format');

		$data = $presence->getHistoricStream(
			$dateRange[0],
			$dateRange[1],
			$this->getRequestSearchQuery(),
			$this->getRequestOrdering(),
			$this->getRequestLimit(),
			$this->getRequestOffset()
		);
        $stream = $data->stream;
        $total = $data->total;

		$tableData = array();
		// convert statuses to appropriate datatables.js format
		if ($presence->isForTwitter()) {
			foreach ($stream as $tweet) {
                $tweet = (object)$tweet;
				$tableData[] = array(
                    'id' => $tweet->id,
					'message' => $format == 'csv' ? $tweet->text_expanded : $tweet->html_tweet,
					'date' => Model_Base::localeDate($tweet->created_time),
                    'links' => $tweet->links,
					'twitter_url' => Model_TwitterTweet::getTwitterUrl($presence->handle, $tweet->tweet_id)
				);
			}
			$count = count($stream);
		} else if ($presence->isForFacebook()) {
			foreach ($stream as $post) {
                $post = (object)$post;
				if($post->message){
					if ($post->first_response) {
						$response = $post->first_response->message;
						$responseDate = $post->first_response->created_time;
					} else {
                        $response = null;
						$responseDate = gmdate('Y-m-d H:i:s');
					}

					$timeDiff = strtotime($responseDate) - strtotime($post->created_time);
					$components = array();
					$timeDiff /= 60;

					$elements = array(
						'minute'=>60,
						'hour'=>24,
						'day'=>100000
					);
					foreach ($elements as $label=>$size) {
						$val = $timeDiff % $size;
						$timeDiff /= $size;
						if ($val) {
							array_unshift($components, $val . ' ' . $label . ($val == 1 ? '' : 's'));
						}
					}

					$tableData[] = array(
						'id' => $post->id,
//						'actor_type' => $post->actor->type,
						'actor_name' => $post->actor->name,
//						'pic_url' => $post->actor->pic_url,
						'facebook_url' => $post->permalink,
						'profile_url' => $post->actor->profile_url,
						'message' => $post->message,
						'links' => $post->links,
						'date' => Model_Base::localeDate($post->created_time),
						'needs_response' => $post->needs_response,
						'first_response' => array(
							'message' => $response,
							'date' => Model_Base::localeDate($responseDate),
							'date_diff' => implode(', ', $components),
						)
					);
				}
			}
			$count = count($stream);
		} else if ($presence->isForSinaWeibo()) {
			foreach ($stream as $post) {
                $post = (object)$post;
				$tableData[] = array(
					'id'	=> $post->id,
					'url'	=> Provider_SinaWeibo::BASEURL . $post->remote_user_id . '/' . Provider_SinaWeibo::getMidForPostId($post->remote_id),
					'message' => $post->text,
                    'links' => $post->links,
					'date' => Model_Base::localeDate($post->created_at)
				);
			}
			$count = count($stream);
		}

		//return CSV or JSON?
		if ($this->_request->getParam('format') == 'csv') {
			$this->returnCsv($tableData, $presence->type.'s.csv');
		} else {
			$apiResult = array(
				'sEcho' => $this->_request->getParam('sEcho'),
				'iTotalRecords' => $count,
				'iTotalDisplayRecords' => $total,
				'aaData' => $tableData
			);
			$this->apiSuccess($apiResult);
		}

	}

	/**
	 * This should be called via a cron job (~hourly), and does not output anything
	 */
	public function updateKpiCacheAction() {
        //moved to be part of build-badge-data
        exit;
	}

    public function downloadAction() {
        Model_PresenceFactory::setDatabase(Zend_Registry::get('db')->getConnection());
        $presences = Model_PresenceFactory::getPresences();

        $csvData = Util_Csv::generateCsvData($presences, $this->tableIndexHeaders());

        Util_Csv::outputCsv($csvData, 'presences');
	    exit;
    }

    /**
     * @return Header_Abstract[]
     */
    protected function tableIndexHeaders() {
        return array(
//            Header_Compare::getInstance(),//todo: reinstate when compare functionality is restored
            Header_Handle::getInstance(),
            Header_SignOff::getInstance(),
            Header_Branding::getInstance(),
            Header_TotalRank::getInstance(),
            Header_TotalScore::getInstance(),
            Header_CurrentAudience::getInstance(),
            Header_TargetAudience::getInstance(),
            Header_ActionsPerDay::getInstance(),
            Header_ResponseTime::getInstance(),
            Header_Options::getInstance()
        );
    }

}
