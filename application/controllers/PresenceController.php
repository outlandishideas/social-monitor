<?php

class PresenceController extends GraphingController
{

	public function indexAction()
	{
        $title = '<span class="'. Model_Presence::ICON_TYPE .' icon-large"></span> Presences';

        $this->view->title = $title;
        $this->view->presences = Model_Presence::fetchAll();
		$this->view->tableMetrics = self::tableMetrics();
	}

	/**
	 * Views a specific presence
	 * @permission view_presence
	 */
	public function viewAction()
	{
		/** @var Model_Presence $presence */
		$presence = Model_Presence::fetchById($this->_request->id);
		$this->validateData($presence);



        $title = (object)array(
            'main'=>$presence->getLabel(),
            'logo'=>'<img src="' . $presence->image_url . '" alt="' . $presence->getLabel() . '"/>'
        );

        if($presence->getLabel() != $presence->handle){
            $title->subtitle = '<a href="'.$presence->page_url.'" target="_blank">' . $presence->handle . ' <span class="icon-external-link"></span></a>';
        }

		$this->view->title = $title;
		$this->view->presence = $presence;
        $this->view->graphs = $this->graphs($presence);
	}

    public function compareAction()
    {
        $compareData = array();
        foreach(explode(',',$this->_request->id) as $id){
	        /** @var Model_Presence $presence */
            $presence = Model_Presence::fetchById($id);
            $this->validateData($presence);
            $compareData[$id] = (object)array(
	            'presence'=>$presence,
	            'graphs'=>$this->graphs($presence)
            );
        }

        $this->view->title = 'Comparing '.count($compareData).' Presences';
	    $this->view->metricOptions = self::graphMetrics();
	    $this->view->tableMetrics = self::tableMetrics();
        $this->view->compareData = $compareData;
    }

	/**
	 * Creates a new presence
	 * @permission create_presence
	 */
	public function newAction()
	{
		// do exactly the same as in editAction, but with a different title
		$this->editAction();
        $this->view->editType = true;
		$this->view->title = 'New presence';
		$this->_helper->viewRenderer->setScriptAction('edit');
	}

	/**
	 * Edits/creates a presence
	 * @permission edit_presence
	 */
	public function editAction()
	{
		if ($this->_request->action == 'edit') {
			$presence = Model_Presence::fetchById($this->_request->id);
		} else {
			$presence = new Model_Presence();
		}

		$this->validateData($presence);

		if ($this->_request->isPost()) {
			$presence->fromArray($this->_request->getParams());

			$errorMessages = array();
			if (empty($this->_request->type)) {
				$errorMessages[] = 'Please choose a type';
			}
			if (empty($this->_request->handle)) {
				$errorMessages[] = 'Please enter a handle';
			}
            if (empty($this->_request->branding)) {
                $presence->_row->branding = 0;
            }

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

			if ($errorMessages) {
				foreach ($errorMessages as $message) {
					$this->_helper->FlashMessenger(array('error'=>$message));
				}
			} else {
				$this->_helper->redirector->gotoSimple('index');
			}
		}

        $this->view->editType = false;
		$this->view->types = array(Model_Presence::TYPE_TWITTER=>'Twitter', Model_Presence::TYPE_FACEBOOK=>'Facebook');
		$this->view->countries = Model_Campaign::fetchAll();
		$this->view->presence = $presence;
		$this->view->title = 'Edit Presence';
	}

	/**
	 * Updates the name, stats, pic etc for the given facebook page
	 * @permission update_presence
	 */
	public function updateAction()
	{
		$presence = Model_Presence::fetchById($this->_request->id);
		$this->validateData($presence);

		$presence->updateInfo();
		$presence->last_updated = gmdate('Y-m-d H:i:s');
		$presence->save();

		$this->_helper->FlashMessenger(array('info'=>'Updated presence info'));
		$this->_helper->redirector->gotoSimple('index');
	}

	/**
	 * Deletes a presence
	 * @permission delete_presence
	 */
	public function deleteAction()
	{
		$presence = Model_Presence::fetchById($this->_request->id);
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
			$presence = Model_Presence::fetchById($chart['presence_id']);
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
					$trafficLight = $this->view->trafficLight();
					foreach ($data['points'] as $point) {
						$point->color = $trafficLight->color(isset($point->health) ? $point->health : $point->value, $metric);
					}
					$data['color'] = $trafficLight->color(isset($data['health']) ? $data['health'] : $data['average'], $metric);
                    $data['chart'] = $chart;
					$series[] = $data;
				}
			}
		}

		$this->apiSuccess($series);
	}

	/**
	 * @param Model_Presence $presence
	 * @param $startDate
	 * @param $endDate
	 * @return array
	 */
	private function generatePopularityGraphData($presence, $startDate, $endDate)
	{
		// subtract 1 from the first day, as we're calculating a daily difference
		$startDate = date('Y-m-d', strtotime($startDate . ' -1 day'));

		$data = $presence->getPopularityData($startDate, $endDate);
		$points = array();
		$target = $presence->getTargetAudience();
		$targetDate = $presence->getTargetAudienceDate($startDate, $endDate);
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
					$interval = date_create($targetDate)->diff(date_create($startDate));
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

			$points = $this->fillDateGaps($points, $startDate, $endDate, 0);
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

		$bc = array();
		$nonBc = array();
		foreach ($postsPerDay as $entry) {
			$date = $entry->date;
			$bc[$date] = (object)array('date'=>$date, 'value'=>0, 'subtitle'=>'(with BC links)', 'statusIds'=>array());
			$nonBc[$date] = (object)array('date'=>$date, 'value'=>0, 'subtitle'=>'(with non-BC links)', 'statusIds'=>array());
		}

		foreach ($presence->getLinkData($startDate, $endDate) as $row) {
			if ($row->is_bc) {
				$bc[$row->date]->statusIds[] = $row->status_id;
			} else {
				$nonBc[$row->date]->statusIds[] = $row->status_id;
			}
		}
		$bc = array_values($bc);
		$nonBc = array_values($nonBc);
		foreach (array($bc, $nonBc) as $set) {
			foreach ($set as $row) {
				$row->value = count(array_unique($row->statusIds));
				unset($row->statusIds);
			}
		}

		return array(
			'average' => $average,
			'target' => $target,
			'points' => $postsPerDay,
			'bc' => $bc,
			'non_bc' => $nonBc
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
			foreach ($data as $row) {
				$key = gmdate('Y-m-d', strtotime($row->post->created_time));
				if (!array_key_exists($key, $points)) {
					$points[$key] = (object)array('date'=>$key, 'value'=>array());
				}

				$diff = ($row->first_response ? strtotime($row->first_response->created_time) : $now) - strtotime($row->post->created_time);
				$diff /= (60*60);
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
		$presence = Model_Presence::fetchById($this->_request->id);
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

	private function fillDateGaps($points, $startDate, $endDate, $value) {
		$currentDate = $startDate;
		while ($currentDate < $endDate) {
			if (!array_key_exists($currentDate, $points)) {
				$points[$currentDate] = (object)array('date'=>$currentDate, 'value'=>$value);
			}
			$currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
		}
		ksort($points);
		return $points;
	}

	/**
	 * This should be called via a cron job (~hourly), and does not output anything
	 */
	public function updateKpiCacheAction() {
		/** @var Model_Presence[] $presences */
		$presences = Model_Presence::fetchAll();
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
		exit;
	}
}
