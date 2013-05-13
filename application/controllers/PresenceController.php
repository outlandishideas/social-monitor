<?php

class PresenceController extends BaseController
{



	public function indexAction()
	{
		$this->view->title = 'All Presences';
//		if ($this->_request->campaign) {
//			$filter = 'campaign_id='. $this->_request->campaign;
//		} else {
			$filter = null;
//		}
		$this->view->presences = Model_Presence::fetchAll($filter);
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

		$graphs = array();
		$graphs[] = (object)array(
			'id' => 'popularity',
			'yAxisLabel' => ($presence->type == Model_Presence::TYPE_FACEBOOK ? 'Fans' : 'Followers') . ' gained per day',
			'lineId' => 'popularity:' . $presence->id,
			'title' => 'Audience Rate'
		);
		$graphs[] = (object)array(
			'id' => 'posts_per_day',
			'yAxisLabel' => 'Posts per day',
			'lineId' => 'posts_per_day:' . $presence->id,
			'title' => 'Posts Per Day'
		);

		$title = '';
		if ($presence->image_url) {
			$title .= '<img src="' . $presence->image_url . '" alt="' . $presence->getLabel() . '"/>';
		}
		$title .= $presence->getLabel();
		if ($presence->getLabel() != $presence->handle) {
			$title .= ' (' . $presence->handle . ')';
		}
		$this->view->title = $title;
		$this->view->presence = $presence;
		$this->view->graphs = $graphs;
	}



	/**
	 * Creates a new presence
	 * @permission create_presence
	 */
	public function newAction()
	{
		// do exactly the same as in editAction, but with a different title
		$this->editAction();
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
		$lineIds = $this->_request->line_ids;
		if (!$lineIds) {
			$this->apiError('Missing line IDs');
		}

		$startDate = $dateRange[0];
		$endDate = $dateRange[1];

		$series = array();

		foreach ($lineIds as $lineId) {
			list($selector, $presenceId) = explode(':', $lineId);
			$presence = Model_Presence::fetchById($presenceId);
			if ($presence) {
				$line = null;
				switch ($selector) {
					case 'popularity':
						$line = $this->generatePopularityGraphData($presence, $startDate, $endDate);
						break;
					case 'posts_per_day':
						$line = $this->generatePostsPerDayGraphData($presence, $startDate, $endDate);
						break;
				}
				if ($line) {
					$line['line_id'] = $lineId;
					$line['selector'] = '#' . $selector;
					$series[] = $line;
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

			$healthCalc = new Util_HealthCalculator($targetDiff);
			$requiredRates = $healthCalc->requiredRates($current->datetime);

			if ($targetDiff > 0) {
				if ($targetDate) {
					$interval = date_create($targetDate)->diff(date_create($startDate));
					$timeToTarget = array('y'=>$interval->y, 'm'=>$interval->m);
					$graphHealth = $healthCalc->getHealth($targetDiff/$interval->days);
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
					$point->health = $healthCalc->getHealth($point->value);
				} else {
					$point->value = 0;
				}
			}

			// fill in the gaps
			$currentDate = $startDate;
			while ($currentDate < $endDate) {
				if (!array_key_exists($currentDate, $points)) {
					$points[$currentDate] = (object)array('date'=>$currentDate, 'value'=>0);
				}
				$currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
			}
			ksort($points);
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
		$data = $presence->getPostsPerDayData($startDate, $endDate);
		usort($data, function($a, $b) { return strcmp($a->date, $b->date); });

		$target = BaseController::getOption('updates_per_day');
		$okRange = BaseController::getOption('updates_per_day_ok_range');
		$badRange = BaseController::getOption('updates_per_day_bad_range');
		$healthCalc = function($value) use ($target, $okRange, $badRange) {
			$targetDiff = abs($value - $target);
			if ($targetDiff <= $okRange) {
				return 100;
			} else if ($targetDiff <= $badRange) {
				return 50;
			} else {
				return 0;
			}
		};
		$average = 0;
		if ($data) {
			$total = 0;
			foreach ($data as $row) {
				$total += $row->post_count;
				$row->health = $healthCalc($row->post_count);
			}
			$average = $total/count($data);
		}

		return array(
			'average' => $average,
			'target' => $target,
			'points' => $data,
			'health' => $healthCalc($average)
		);
	}

	public static function getStatusType($id){
		return Model_Presence::fetchById($id)->typeLabel;
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

		$lineIds = explode(',', $this->_request->line_ids);
		$linePropsArray = array();
		foreach ($lineIds as $lineId) {
			if ($lineId) $linePropsArray[] = self::parseLineId($lineId);
		}

		$tableData = array();
		$count = 0;
		$data = null;
		if ($linePropsArray) {
			/** @var $presence Model_Presence */
			$presence = Model_Presence::fetchById($linePropsArray[0]['modelId']);
			$data = $presence->getStatuses(
				$dateRange[0],
				$dateRange[1],
				$this->getRequestSearchQuery(),
				$this->getRequestOrdering(),
				$this->getRequestLimit(),
				$this->getRequestOffset()
			);

			// convert statuses to appropriate datatables.js format
			if ($presence->type == Model_Presence::TYPE_TWITTER) {
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
						$tableData[] = array(
							'actor_type' => $post->actor->type,
							'actor_name' => $post->actor->name,
							'pic_url' => $post->actor->pic_url,
							'facebook_url' => $post->permalink,
							'profile_url' => $post->actor->profile_url,
							'message' => $post->message,
							'date' => Model_Base::localeDate($post->created_time)
						);
					}
				}
			}
			$count = count($data->statuses);
		}

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

}
