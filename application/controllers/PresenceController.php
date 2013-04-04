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

		$this->view->title = $presence->label;
		$this->view->presence = $presence;
        $this->view->defaultLineId = self::makeLineId('Model_Presence', $presence->id);
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

    //fetch mentions data for line graph
    public function graphDataAction() {
        Zend_Session::writeClose(); //release session on long running actions

        $dateRange = $this->getRequestDateRange();
        if (!$dateRange) {
            $this->apiError('Missing date range');
        }

        if (empty($this->_request->line_ids)) {
            $this->apiError('Missing line ID(s)');
        }

        $startDate = new DateTime($dateRange[0]);
        $endDate = new DateTime($dateRange[1]);

        $days = $startDate->diff($endDate)->days;

        $series = array();
        $lineIds = is_array($this->_request->line_ids) ? $this->_request->line_ids : array($this->_request->line_ids);

        foreach ($lineIds as $lineId) {
            $lineProps = self::parseLineId($lineId);

            $model = $lineProps['modelClass']::fetchById($lineProps['modelId']);
            $selector = '#popularity';
            $name = 'Popularity for '.$model->name;

            $buckets = $model->getPopularityData($days);

            //key data by date
            $keyedBuckets = array();
            foreach ($buckets as $bucket) {
                //$bucket['date'] = Model_Base::localeDate($bucket['date']);
                $keyedBuckets[$bucket->datetime] = $bucket;
            }
            $country = $model->getCountry();
            if($country){
                $target =0.5*$country->audience;
            } else {
                $target = 30000;
            }

            //combine arrays
            ksort($keyedBuckets);
            $series[] = array(
                'name' => $name,
                'selector' => $selector,
                'line_id' => $lineId,
                'target' => $target,
                'timeToTarget' => $model->getTargetAudienceDate(),
                'timeToTargetPercent' => $model->getTargetAudienceDatePercent(),
                'points' => array(array_values($keyedBuckets))
            );
        }

        return $this->apiSuccess($series);
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
            $modelClass = $linePropsArray[0]['modelClass'];
            $statusType = $modelClass::getStatusType($linePropsArray[0]['modelId']);
            $statuses = $modelClass::getStatusesForModelIds(
                Zend_Registry::get('db'),
                $linePropsArray,
                $dateRange,
                $this->getRequestSearchQuery(),
                $this->getRequestOrdering(),
                $this->getRequestLimit(),
                $this->getRequestOffset()
            );

            // convert statuses to appropriate datatables.js format
            if ($statusType == 'tweet') {
                foreach ($statuses->data as $tweet) {

                    $tableData[] = array(
                        'user_name'=>$tweet->user_name,
                        'screen_name'=>$tweet->screen_name,
                        'tweet'=> $this->_request->format == 'csv' ? $tweet->text_expanded : $tweet->html_tweet,
                        'retweet_count'=>$tweet->retweet_count,
                        'average_sentiment'=>($tweet->average_sentiment ? $tweet->average_sentiment : 0),
                        'date'=>Model_Base::localeDate($tweet->created_time),
                        'twitter_url'=>Model_TwitterTweet::getTwitterUrl($tweet->screen_name, $tweet->tweet_id),
                        'profile_image_url'=>$tweet->profile_image_url
                    );
                }
            } else {
                foreach ($statuses->data as $post) {
                    $tableData[] = array(
                        'actor_type'=>$post->actor_type,
                        'actor_name' => $post->actor_name,
                        'pic_url' => $post->pic_url,
                        'profile_url' => $post->profile_url,
                        'message'=>$post->message,
                        'average_sentiment'=>($post->average_sentiment ? $post->average_sentiment : 0),
                        'comments'=>$post->comments,
                        'likes'=>$post->likes,
                        'date'=> Model_Base::localeDate($post->created_time)
                    );
                }
            }
            $count = $statuses->count;
        }

        //return CSV or JSON?
        if ($this->_request->format == 'csv') {
            $this->returnCsv($tableData, $statusType.'s.csv');
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
