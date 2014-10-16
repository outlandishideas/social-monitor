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
//		$presences = Model_Presence::populateOwners(Model_Presence::fetchAll());

		NewModel_PresenceFactory::setDatabase(Zend_Registry::get('db')->getConnection());
		$presences = NewModel_PresenceFactory::getPresences();

        $this->view->title = 'Presences';
        $this->view->titleIcon = Model_Presence::ICON_TYPE;
        $this->view->presences = $presences;
        $this->view->tableHeaders = self::generateTableHeaders();
		$this->view->tableMetrics = self::tableMetrics();
        $this->view->badgeData = Model_Badge::badgesData(true);
	}

	/**
	 * Views a specific presence
	 */
	public function viewAction()
	{
		/** @var Model_Presence $presence */
		$presence = NewModel_PresenceFactory::getPresenceById($this->_request->id);
		$this->validateData($presence);

		$this->view->badgePartial = $this->badgeDetails($presence->getBadges());
		$this->view->metricOptions = $this->graphMetrics();
		$this->view->presence = $presence;
	}

	/**
	 * Compares multiple presences
	 * @user-level user
	 */
	public function compareAction()
    {
        $compareData = array();
        foreach(explode(',',$this->_request->id) as $id){
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
	    $this->view->metricOptions = self::graphMetrics();
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
		if ($this->_request->action == 'edit') {
			$presence = Model_Presence::fetchById($this->_request->id);
			if($this->_request->type == NewModel_PresenceType::SINA_WEIBO){
				$presence = NewModel_PresenceFactory::getPresenceById($this->_request->id);
			}
            $this->view->showButtons = true;
		} else {
			$presence = new Model_Presence();
            $this->view->showButtons = false;
		}

		$this->validateData($presence);

		if ($this->_request->isPost()) {

			$errorMessages = array();
			if (empty($this->_request->type)) {
				$errorMessages[] = 'Please choose a type';
			}
			if (empty($this->_request->handle)) {
				$errorMessages[] = 'Please enter a handle';
			}

			$typeName = $this->_request->type;
			if($typeName == "SINA_WEIBO"){

				$type = NewModel_PresenceType::SINA_WEIBO();
				if($presence instanceof Model_Presence){
					$handle = $this->_request->handle;
					$signOff = $this->_request->sign_off;
					$branding = $this->_request->branding;

					NewModel_PresenceFactory::setDatabase(Zend_Registry::get('db')->getConnection());

					try {
						NewModel_PresenceFactory::createNewPresence($type, $handle, $signOff, $branding);
					} catch (Exception $ex) {
						if (strpos($ex->getMessage(), '23000') !== false) {
							$errorMessages[] = 'Presence already exists';
						} else {
							$errorMessages[] = $ex->getMessage();
						}
					}
					$presence = NewModel_PresenceFactory::getPresenceByHandle($handle, $type);
				}

			} else {

				$presence->fromArray($this->_request->getParams());

				if (!$errorMessages) {
					try {
						$presence->updateInfo();
						$presence->last_updated = gmdate('Y-m-d H:i:s');
						$presence->save();

						$this->_helper->FlashMessenger(array('info' => 'Presence saved'));
						$this->_helper->redirector->gotoSimple('index');
					} catch (Exception $ex) {
						if (strpos($ex->getMessage(), '23000') !== false) {
							$errorMessages[] = 'Presence already exists';
						} else {
							$errorMessages[] = $ex->getMessage();
						}
					}
				}

			}

			if ($errorMessages) {
				foreach ($errorMessages as $message) {
					$this->_helper->FlashMessenger(array('error'=>$message));
				}
			} else {
				$this->_helper->redirector->gotoSimple('index');
			}
		}

        $this->view->editType = false;
		$this->view->types = NewModel_PresenceType::toArray();
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
		$presence = NewModel_PresenceFactory::getPresenceById($this->_request->id);
		$this->validateData($presence, 'page');

		if ($this->_request->isPost()) {
			$presence->delete();
			$this->_helper->FlashMessenger(array('info' => 'Presence deleted'));
		}
		$this->_helper->redirector->gotoSimple('index');
	}

    /**
     * Gets all of the graph data for the requested presence
     */
    public function badgeDataAction() {
        Zend_Session::writeClose(); // release session on long running actions

	    /** @var Model_Presence $presence */
        $presence = NewModel_PresenceFactory::getPresenceById($this->_request->id);

        $response = $presence->badges();

        $this->apiSuccess($response);
    }

	/**
	 * Gets all of the graph data for the requested presence
	 */
	public function graphDataAction() {
		Zend_Session::writeClose(); //release session on long running actions

		$this->validateChartRequest();

		/** @var $presence NewModel_Presence */
		$presence = NewModel_PresenceFactory::getPresenceById($this->_request->id);
		if(!$presence) {
			$this->apiError('Presence could not be found');
		}

		$dateRange = $this->getRequestDateRange();
		$start = $dateRange[0];
		$end = $dateRange[1];

		$chartObject = Chart_Factory::getChart($this->_request->chart);

		$this->apiSuccess($chartObject->getChart($presence, $start, $end));
	}

	/**
	 * AJAX function for toggling whether a facebook status needs a response
	 */
	public function toggleResponseNeededAction() {
		$id = $this->_request->id;
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
		$presence = NewModel_PresenceFactory::getPresenceById($this->_request->id);
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
					'message'=> $this->_request->format == 'csv' ? $tweet->text_expanded : $tweet->html_tweet,
					'date'=>Model_Base::localeDate($tweet->created_time),
					'twitter_url'=>Model_TwitterTweet::getTwitterUrl($presence->handle, $tweet->tweet_id)
				);
			}
		} else {
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
		}
		$count = count($data->statuses);

		//return CSV or JSON?
		if ($this->_request->format == 'csv') {
			$this->returnCsv($tableData, $presence->type.'s.csv');
		} else {
			$apiResult = array(
				'sEcho' => $this->_request->sEcho,
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
		$startDate->sub(DateInterval::createFromDateString('1 month'));
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
        /*if (userHasNoPermissions) {
            $this->view->msg = 'This file cannot be downloaded!';
            $this->_forward('error', 'download');
            return FALSE;
        }*/

        $data = array();

	    $columns = array();
        $headers = array();
        foreach(self::tableIndexHeaders() as $key=>$csv){
	        if ($csv) {
		        $column = self::tableHeader($key, $csv);
		        $columns[] = $column;
                $headers[] = $column->title;
            }
        }

        $data[] = $headers;

        $badgeData = Model_Badge::badgesData(true);
        $presences = Model_Presence::fetchAll();

        foreach($presences as $presence){
            $row = array();
            $currentBadge = $badgeData[$presence->id];
            $kpiData = $presence->getKpiData();
            foreach($columns as $column){
                $output = null;
                switch($column->name){
                    case('handle'):
                        $output = $presence->handle;
                        break;
                    case('sign-off'):
                        $output = $presence->sign_off;
                        break;
                    case('branding'):
                        $output = $presence->branding;
                        break;
                    case('total-rank'):
                        $output = (int)$currentBadge->total_rank;
                        break;
                    case('total-score'):
                        $output = (float)round($currentBadge->total);
                        break;
                    case('current-audience'):
                        $output = number_format($presence->popularity);
                        break;
                    case('target-audience'):
                        $output = number_format($presence->getTargetAudience());
                        break;
                    default:
                        if( array_key_exists($column->name, $kpiData) ){
                            $output = $kpiData[$column->name];
                        }
                }
                $row[] = $output;
            }

            $data[] = $row;

        }

	    header("Content-type: text/csv");
	    header("Content-Disposition: attachment; filename=presence_index.csv");
	    header("Pragma: no-cache");
	    header("Expires: 0");

	    self::outputCSV($data);
	    exit;
    }

    function outputCSV($data) {
        $output = fopen("php://output", "w");
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
    }

    public static function tableIndexHeaders() {

        $return = array(
            'compare' => false,
            'handle' => true,
            'sign-off' => true,
            'branding' => true,
            'total-rank' => true,
            'total-score' => true,
            'current-audience' => true,
            'target-audience' => true
        );

        foreach(self::tableMetrics() as $name => $title){
            $return[$name] = true;
        }

        $return['options'] = false;

        return $return;
    }
}
