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
		$presence = Model_Presence::fetchById($this->_request->id);
		$this->validateData($presence);

		$graphs = array();
		$graphs[] = (object)array(
			'id' => 'popularity',
			'yAxisLabel' => 'fans/followers',
			'lineId' => 'popularity:' . $presence->id,
			'title' => 'Target Followers',
			'legend' => 'Current'
		);
		$graphs[] = (object)array(
			'id' => 'posts_per_day',
			'yAxisLabel' => 'posts-per-day',
			'lineId' => 'posts_per_day:' . $presence->id,
			'title' => 'Posts Per Day',
			'legend' => 'Average'
		);

		$this->view->title = $presence->label;
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
				switch ($selector) {
					case 'popularity':
						$buckets = $presence->getPopularityData($startDate, $endDate);

						$keyedBuckets = array();
						if ($buckets) {
							//key data by date
							foreach ($buckets as $bucket) {
								//$bucket['date'] = Model_Base::localeDate($bucket['date']);
								$keyedBuckets[$bucket->datetime] = $bucket;
							}
							ksort($keyedBuckets);
						}

						$series[] = array(
							'line_id' => $lineId,
							'selector' => '#' . $selector,
							'target' => $presence->getTargetAudience(),
							'timeToTarget' => $presence->getTargetAudienceDate(),
							'points' => array_values($keyedBuckets),
							'current' => intval($presence->popularity)
						);
						break;
					case 'posts_per_day':
						$data = $presence->getPostsPerDayData($startDate, $endDate);
						usort($data, function($a, $b) { return strcmp($a->date, $b->date); });

						$series[] = array(
							'line_id' => $lineId,
							'selector' => '#' . $selector,
							'points' => $data
						);
						break;
				}
			}
		}

		$this->apiSuccess($series);
	}

	public static function getStatusType($id){
		return Model_Presence::fetchById($id)->typeLabel;
	}

	// AJAX function for fetching the posts/tweets for a page/list/search
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
		if ($linePropsArray) {
			$presence = Model_Presence::fetchById($linePropsArray[0]['modelId']);
			$statuses = $presence->getStatuses($dateRange[0],$dateRange[1]);

			// convert statuses to appropriate datatables.js format
			if ($presence->type == Model_Presence::TYPE_TWITTER) {
				foreach ($statuses as $tweet) {

					$tableData[] = array(
						'user_name'=>$tweet->user_name,
						'screen_name'=>$tweet->screen_name,
						'message'=> $this->_request->format == 'csv' ? $tweet->text_expanded : $tweet->html_tweet,
						'likes'=>$tweet->retweet_count,
						'date'=>Model_Base::localeDate($tweet->created_time),
						'profile_url'=>Model_TwitterTweet::getTwitterUrl($tweet->screen_name, $tweet->tweet_id),
						'profile_image_url'=>$tweet->profile_image_url
					);
				}
			} else {
				foreach ($statuses as $post) {
                    if($post->message){
                        $tableData[] = array(
                            'actor_type'=>'type',//$post->actor->actor_type
                            'actor_name' => $post->actor_id,//$post->actor->name
                            'pic_url' => '../../../assets/img/facebook.img',//$post->actor->pic_url
                            'profile_url' => 'http://www.facebook.com',//$post->actor->profile_url
                            'message'=>$post->message,
                            'comments'=>$post->comments,
                            'likes'=>$post->likes,
                            'date'=> Model_Base::localeDate($post->created_time)
                        );
                    }
				}
			}
			$count = count($statuses);
		}

		//return CSV or JSON?
		if ($this->_request->format == 'csv') {
			$this->returnCsv($tableData, $presence->type.'s.csv');
		} else {
			$apiResult = array(
				'sEcho' => $this->_request->sEcho,
				'iTotalRecords' => $count,
				'iTotalDisplayRecords' => $count,
				'aaData' => $tableData
			);
			$this->apiSuccess($apiResult);
		}

	}




}
