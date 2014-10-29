<?php

class PresenceController extends GraphingController
{
	protected static $publicActions = array('update-kpi-cache');

	/**
	 * Lists all presences
	 * @user-level user
	 */
	public function indexAction()
	{
		NewModel_PresenceFactory::setDatabase(Zend_Registry::get('db')->getConnection());
		$presences = NewModel_PresenceFactory::getPresences();

        $this->view->title = 'Presences';
        $this->view->presences = $presences;
        $this->view->tableHeaders = $this->tableIndexHeaders();
        $this->view->sortCol = Header_Handle::getName();
	}

	/**
	 * Views a specific presence
	 */
	public function viewAction()
	{
		/** @var NewModel_Presence $presence */
		$presence = NewModel_PresenceFactory::getPresenceById($this->_request->getParam('id'));
		$this->validateData($presence);

		$this->view->presence = $presence;
		$this->view->badgePartial = $this->badgeDetails($presence->getBadges());
		$this->view->chartOptions = $this->chartOptions();
        $allPresences = array();
        foreach (NewModel_PresenceFactory::getPresences() as $p) {
            $group = $p->getType()->getTitle();
            if (!isset($allPresences[$group])) {
                $allPresences[$group] = array();
            }
            $allPresences[$group][] = $p;
        }
        $this->view->allPresences = $allPresences;
	}

	/**
	 * Compares multiple presences
	 * @user-level user
	 */
	public function compareAction()
    {
        $compareData = array();
        foreach(explode(',',$this->_request->getParam('id')) as $id){
	        /** @var Model_Presence $presence */
            $presence = NewModel_PresenceFactory::getPresenceById($id);
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
		$this->_helper->viewRenderer->setScriptAction('edit');
	}

	/**
	 * Edits/creates a presence
	 * @user-level user
	 */
	public function editAction()
	{
        NewModel_PresenceFactory::setDatabase(Zend_Registry::get('db')->getConnection());

		if ($this->_request->getActionName() == 'edit') {
            $presence = NewModel_PresenceFactory::getPresenceById($this->_request->getParam('id'));
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
                        $type = NewModel_PresenceType::get($type);
                        $presence = NewModel_PresenceFactory::createNewPresence($type, $handle, $signOff, $branding);
                    } else {
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
		$this->view->types = NewModel_PresenceType::enumValues();
		$this->view->countries = Model_Country::fetchAll();
        $this->view->groups = Model_Group::fetchAll();
		$this->view->presence = $presence;
		$this->view->title = 'Edit Presence';
		$this->view->titleIcon = 'icon-edit';
	}

	/**
	 * Deletes a presence
	 * @user-level manager
	 */
	public function deleteAction()
	{
		$presence = NewModel_PresenceFactory::getPresenceById($this->_request->getParam('id'));
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

		/** @var $presence NewModel_Presence */
		$presence = NewModel_PresenceFactory::getPresenceById($this->_request->getParam('id'));
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

		/** @var $presence Model_Presence */
		$presence = NewModel_PresenceFactory::getPresenceById($this->_request->getParam('id'));
		if (!$presence) {
			$this->apiError('Presence not found');
		}

		$data = $presence->getStatuses(
			$dateRange[0],
			$dateRange[1],
			$this->getRequestSearchQuery(),
			$this->getRequestOrdering(),
			$this->getRequestLimit(),
			$this->getRequestOffset()
		);

		$tableData = array();
		// convert statuses to appropriate datatables.js format
		if ($presence->isForTwitter()) {
			foreach ($data->statuses as $tweet) {
				$tableData[] = array(
					'message'=> $this->_request->getParam('format') == 'csv' ? $tweet->text_expanded : $tweet->html_tweet,
					'date'=>Model_Base::localeDate($tweet->created_time),
					'twitter_url'=>Model_TwitterTweet::getTwitterUrl($presence->handle, $tweet->tweet_id)
				);
			}
			$count = count($data->statuses);
		} else if ($presence->isForFacebook()) {
			foreach ($data->statuses as $post) {
				if($post->message){
					if ($post->first_response) {
						$message = $post->first_response->message;
						$responseDate = $post->first_response->created_time;
					} else {
						$message = null;
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
						'actor_type' => $post->actor->type,
						'actor_name' => $post->actor->name,
						'pic_url' => $post->actor->pic_url,
						'facebook_url' => $post->permalink,
						'profile_url' => $post->actor->profile_url,
						'message' => $post->message,
						'links' => $post->links,
						'date' => Model_Base::localeDate($post->created_time),
						'needs_response' => $post->needs_response,
						'first_response' => array(
							'message'=>$message,
							'date' => Model_Base::localeDate($responseDate),
							'date_diff' => implode(', ', $components),
						)
					);
				}
			}
			$count = count($data->statuses);
		} else if ($presence->isForSinaWeibo()) {
			foreach ($data as $post) {
				$tableData[] = array(
					'id'	=> $post['id'],
					'url'	=> NewModel_SinaWeiboProvider::BASEURL . $post['remote_user_id'] . '/' . NewModel_SinaWeiboProvider::getMidForPostId($post['remote_id']),
					'message' => $post['text'],
					'date' => Model_Base::localeDate($post['created_at'])
				);
			}
			$count = count($data);
			$data = (object) array('total' => $count); //todo: fix this to have a real total
		}

		//return CSV or JSON?
		if ($this->_request->getParam('format') == 'csv') {
			$this->returnCsv($tableData, $presence->type.'s.csv');
		} else {
			$apiResult = array(
				'sEcho' => $this->_request->getParam('sEcho'),
				'iTotalRecords' => $count,
				'iTotalDisplayRecords' => $data->total,
				'aaData' => $tableData
			);
			$this->apiSuccess($apiResult);
		}

	}

	private function fillDateGaps($points, DateTime $start, DateTime $end, $value) {
		$currentDate = clone $start;
		while ($currentDate < $end) {
			$currentDateKey = $currentDate->format("Y-m-d");
			if (!array_key_exists($currentDateKey, $points)) {
				$points[$currentDateKey] = (object)array('date'=>$currentDateKey, 'value'=>$value);
			}
			$currentDate->modify('+1 day');
		}
		ksort($points);
		return $points;
	}

	/**
	 * This should be called via a cron job (~hourly), and does not output anything
	 */
	public function updateKpiCacheAction() {
		/** @var NewModel_Presence[] $presences */
		$presences = NewModel_PresenceFactory::getPresences();
		$endDate = new DateTime();
		$startDate = new DateTime();
		$startDate->sub(DateInterval::createFromDateString('30 days'));
		$stmt = self::db()->prepare('REPLACE INTO kpi_cache (presence_id, metric, start_date, end_date, value) VALUES (:pid, :metric, :start, :end, :value)');
		$args = array(
			':start'=>$startDate->format('Y-m-d'),
			':end'=>$endDate->format('Y-m-d')
		);
		foreach ($presences as $p) {
			$args[':pid'] = $p->id;
			$stats = $p->getKpiData($startDate, $endDate, false);
			foreach ($stats as $metric=>$value) {
				$args[':metric'] = $metric;
				$args[':value'] = $value;
				$stmt->execute($args);
			}
		}
		print_r($presences);
		exit;
	}

    public function downloadAction() {
        NewModel_PresenceFactory::setDatabase(Zend_Registry::get('db')->getConnection());
        $presences = NewModel_PresenceFactory::getPresences();

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
            Header_Options::getInstance()
        );
    }
}
