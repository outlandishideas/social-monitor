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
		NewModel_PresenceFactory::setDatabase(Zend_Registry::get('db')->getConnection());
		$presence = NewModel_PresenceFactory::getPresenceById($this->_request->id);
//		$presence = Model_Presence::fetchById($this->_request->id);
		$this->validateData($presence);

		$this->view->title = $presence->getLabel();
		if($presence->getLabel() != $presence->handle){
			$this->view->subtitle = '<a href="'.$presence->page_url.'" target="_blank">' . $presence->handle . ' <span class="icon-external-link"></span></a>';
		}

        if($presence->sign_off){
            $this->view->title .= ' <span class="icon-ok-sign" title="Has been signed off by Head of Digital"></span>';
        } else {
            $this->view->title .= ' <span class="icon-remove-sign" title="Has not been signed off by Head of Digital"></span>';
        }

        if($presence->branding){
            $this->view->title .= ' <span class="icon-ok-sign" title="Has been correctly branded"></span>';
        } else {
            $this->view->title .= ' <span class="icon-remove-sign" title="Is lacking correct branding"></span>';
        }
        
		$this->view->titleImage = '<img src="' . $presence->image_url . '" alt="' . $presence->getLabel() . '"/>';
		$this->view->presence = $presence;
        $this->view->graphs = $this->graphs($presence);
        $this->view->badges = Model_Badge::$ALL_BADGE_TYPES;
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

		$dateRange = $this->getRequestDateRange();
		if (!$dateRange) {
			$this->apiError('Missing date range');
		}
		/** @var $presence Model_Presence */
        $chartData = $this->_request->chartData;
		if (!$chartData) {
			$this->apiError('Missing chart options');
		}

		$startDate = $dateRange[0];
		$endDate = $dateRange[1];

		$series = array();

		foreach ($chartData as $chart) {
			$presence = NewModel_PresenceFactory::getPresenceById($chart['presence_id']);
			if ($presence) {
                $data = null;
				$metric = $chart['metric'];
				switch ($metric) {
					case Model_Presence::METRIC_POPULARITY_RATE:
						$data = $this->generatePopularityGraphData($presence, $startDate, $endDate);
						break;
					case Model_Presence::METRIC_POSTS_PER_DAY:
                        $data = $this->generatePostsPerDayGraphData($presence, $startDate, $endDate);
						break;
					case Model_Presence::METRIC_RESPONSE_TIME:
						$data = $this->generateResponseTimeGraphData($presence, $startDate, $endDate);
						break;
				}
				if ($data) {
					/** @var Zend_View_Helper_TrafficLight $trafficLight */
					$trafficLight = $this->view->trafficLight();
					foreach ($data['points'] as $point) {
						$point->color = $trafficLight->color(isset($point->health) ? $point->health : $point->value, $metric);
					}
					$data['color'] = $trafficLight->color(isset($data['health']) ? $data['health'] : $data['average'], $metric);
                    if(isset($data['rAverage'])) $data['rColor'] = $trafficLight->color($data['rAverage'], Model_Presence::METRIC_RELEVANCE);
                    $data['chart'] = $chart;
					$series[] = $data;
				}
			}
		}

		$this->apiSuccess($series);
	}

	/**
	 * @param NewModel_Presence $presence
	 * @param DateTime $start
	 * @param DateTime $end
	 * @return array
	 */
	private function generatePopularityGraphData(NewModel_Presence $presence, DateTime $start, DateTime $end)
	{
		// subtract 1 from the first day, as we're calculating a daily difference
		$start = clone $start;
		$end = clone $end;
		$start->modify('-1 day');

		$data = $presence->getPopularityData($start, $end);
		$points = array();
		$target = $presence->getTargetAudience();
		$targetDate = $presence->getTargetAudienceDate($start, $end);
		$graphHealth = 100;
		$requiredRates = null;
		$timeToTarget = null;

		if ($data) {
			$current = $data[count($data)-1];

			$targetDiff = $target - $current->value;

			$bestScore = BaseController::getOption('achieve_audience_best');
			$goodScore = BaseController::getOption('achieve_audience_good');
			$badScore = BaseController::getOption('achieve_audience_bad');

			$daysPerMonth = 365/12;
			$bestRate = $targetDiff/($daysPerMonth*$bestScore);
			$goodRate = $targetDiff/($daysPerMonth*$goodScore);
			$badRate = $targetDiff/($daysPerMonth*$badScore);

			$healthCalc = function($value) use ($bestRate, $goodRate, $badRate, $targetDiff) {
				if ($targetDiff < 0 || $value >= $bestRate) {
					return 100;
				} else if ($value < 0 || $value <= $badRate) {
					return 0;
				} else if ($value >= $goodRate) {
					return 50 + 50*($value - $goodRate)/($bestRate - $goodRate);
				} else {
					return 50*($value - $badRate)/($goodRate - $badRate);
				}
			};
			$requiredRates = array();
			if ($bestRate > 0) {
				$requiredRates[] = array('rate'=>$bestRate, 'date'=>date('F Y', strtotime($current->datetime . ' +' . $bestScore . ' months')));
			}
			if ($goodRate > 0) {
				$requiredRates[] = array('rate'=>$goodRate, 'date'=>date('F Y', strtotime($current->datetime . ' +' . $goodScore . ' months')));
			}
			if ($badRate > 0) {
				$requiredRates[] = array('rate'=>$badRate, 'date'=>date('F Y', strtotime($current->datetime . ' +' . $badScore . ' months')));
			}

			if ($targetDiff > 0) {
				if ($targetDate) {
					$interval = $targetDate->diff($start);
					$timeToTarget = array('y'=>$interval->y, 'm'=>$interval->m);
					$graphHealth = $healthCalc($targetDiff/$interval->days);
				}
			} else {
				$graphHealth = 100;
			}

			foreach ($data as $point) {
				$key = gmdate('Y-m-d', strtotime($point->datetime));
				$points[$key] = $point->value; // overwrite any previous value, as data is sorted by datetime ASC
			}

			foreach ($points as $key=>$value) {
				$points[$key] = (object)array('date'=>$key, 'total'=>$value);
			}

			foreach ($points as $key=>$point) {
				$prevDay = date('Y-m-d', strtotime($key . ' -1 day'));
				if (array_key_exists($prevDay, $points)) {
					$point->value = $point->total - $points[$prevDay]->total;
					$point->health = $healthCalc($point->value);
				} else {
					$point->value = 0;
				}
			}

			$points = $this->fillDateGaps($points, $start, $end, 0);
			$points = array_values($points);

			$current = array(
				'value'=>$current->value,
				'date'=>gmdate('d F Y', strtotime($current->datetime))
			);
		} else {
			$current = null;
		}

		return array(
			'target' => $target,
			'timeToTarget' => $timeToTarget,
			'points' => $points,
			'current' => $current,
			'health' => $graphHealth,
			'requiredRates' => $requiredRates
		);
	}

	/**
	 * @param Model_Presence $presence
	 * @param $startDate
	 * @param $endDate
	 * @return array
	 */
	private function generatePostsPerDayGraphData($presence, $startDate, $endDate) {
		$postsPerDay = $presence->getPostsPerDayData($startDate, $endDate);
		usort($postsPerDay, function($a, $b) { return strcmp($a->date, $b->date); });

		$target = BaseController::getOption('updates_per_day');
		$average = 0;
		if ($postsPerDay) {
			$total = 0;
			foreach ($postsPerDay as $row) {
				$total += $row->value;
			}
			$average = $total/count($postsPerDay);
		}

		$relevance = array();
		foreach ($postsPerDay as $entry) {
			$date = $entry->date;
			$relevance[$date] = (object)array('date'=>$date, 'value'=>0, 'subtitle'=>'Relevance', 'statusIds'=>array());
		}

		foreach ($presence->getRelevanceData($startDate, $endDate) as $row) {
            $relevance[$row->created_time]->value = $row->total_bc_links;
		}
        $rAverage = 0;
		if(count($relevance) > 0){
			foreach($relevance as $r){
				$rAverage += $r->value;
			}
			$rAverage /= count($relevance);
		}
        $relevancePercentage = $presence->isForFacebook() ? 'facebook_relevance_percentage' : 'twitter_relevance_percentage';

		return array(
            'average' => $average,
            'rAverage' => $rAverage,
			'target' => $target,
            'rTarget' => ($target/100)*BaseController::getOption($relevancePercentage),
			'points' => $postsPerDay,
			'relevance' => array_values($relevance),
		);
	}

	/**
	 * @param Model_Presence $presence
	 * @param $startDate
	 * @param $endDate
	 * @return array
	 */
	private function generateResponseTimeGraphData($presence, $startDate, $endDate) {
		$data = $presence->getResponseData($startDate, $endDate);
		$points = array();
		$goodTime = floatval(BaseController::getOption('response_time_good'));
		$badTime = floatval(BaseController::getOption('response_time_bad'));

		if (!$data) {
			$average = 0;
		} else {
			$now = time();
			$totalTime = 0;
			foreach ($data as $id => $row) {
				$key = gmdate('Y-m-d', strtotime($row->created));
				if (!array_key_exists($key, $points)) {
					$points[$key] = (object)array('date'=>$key, 'value'=>array());
				}

				$diff = $row->diff;
				$diff = min($badTime, $diff);
				$totalTime += $diff;
				$points[$key]->value[] = $diff;
			}
			$average = $totalTime/count($data);

			foreach ($points as $key=>$diffs) {
				$points[$key]->value = round(10*array_sum($diffs->value)/count($diffs->value))/10;
			}
			$points = $this->fillDateGaps($points, $startDate, $endDate, 0);
			$points = array_values($points);
		}

		return array(
			'average' => $average,
			'target' => $goodTime,
			'points' => $points,
		);
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
