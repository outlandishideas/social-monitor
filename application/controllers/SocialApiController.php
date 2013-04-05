<?php

// base class for twitter and facebook controllers. Shared functionality (in particular 
// ajax functions) should go in here
abstract class SocialApiController extends BaseController
{
	
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
		$rangeInterval = $startDate->diff($endDate);
		// cannot use $rangeInterval->days, as this is broken in php 5.3 on windows
		if ($rangeInterval->y > 0 || $rangeInterval->m > 0 || $rangeInterval->d > 14) {
			$bucketCol = 'bucket_day';
		} else if ($rangeInterval->d <= 2) {
			$bucketCol = 'bucket_half_hour';
		} else if ($rangeInterval->d <= 7) {
			$bucketCol = 'bucket_4_hours';
		} else { // between 7 and 14 days
			$bucketCol = 'bucket_12_hours';
		}

		$series = array();
		$lineIds = is_array($this->_request->line_ids) ? $this->_request->line_ids : array($this->_request->line_ids);

		//create empty data points
		$emptyBuckets = array();
		$time = $startDate->getTimestamp();
		$bucketSize = Model_SocialApiBase::$bucketSizes[$bucketCol];
		$tz = new DateTimeZone(date_default_timezone_get());
		while ($time <= $endDate->getTimestamp()) {
			$offset = $tz->getOffset($startDate) - $tz->getOffset(DateTime::createFromFormat('U', $time));
			$timeString = date('Y-m-d H:i:s', $time + $offset);
			$emptyBuckets[$timeString] = array('mentions' => 0, 'polarity' => 0, 'date' => $timeString);
			$time += $bucketSize;
		}

		foreach ($lineIds as $lineId) {
			$lineProps = self::parseLineId($lineId);

			$buckets = $lineProps['modelClass']::getMentionsForModelIds(
				Zend_Registry::get('db'),
				$lineProps['modelId'],
				$dateRange,
				$lineProps['filterType'],
				$lineProps['filterValue'],
				$bucketCol
			);

			//key data by date
			$keyedBuckets = array();
			foreach ($buckets as $bucket) {
				$bucket['date'] = Model_Base::localeDate($bucket['date']);
				$keyedBuckets[$bucket['date']] = $bucket;
			}

			//name the series
			if ($lineProps['filterValue']) {
				$name = $lineProps['filterValue'];
			} else {
				$model = $lineProps['modelClass']::fetchById($lineProps['modelId']);
				$name = 'All '.$model->name;
			}

			//combine arrays
			$mergedBuckets = array_merge($emptyBuckets, $keyedBuckets);
			ksort($mergedBuckets);
			$series[] = array(
				'name' => $name,
				'line_id' => $lineId,
				'points' => array(array_values($mergedBuckets))
			);
		}

		return $this->apiSuccess($series);
	}

	// AJAX function for fetching topics to display in topics table for a list/search/facebook page
	public function topicsAction() {
		Zend_Session::writeClose(); //release session on long running actions

		$dateRange = $this->getRequestDateRange();
		if (!$dateRange) {
			$this->apiError('Missing date range');
		}

		if (empty($this->_request->line_ids)) {
			$this->apiError('Missing line ID(s)');
		}

		$lineIds = is_array($this->_request->line_ids) ? $this->_request->line_ids : array($this->_request->line_ids);
		$lineProps = self::parseLineId($lineIds[0]);

		$groupedTopics = $lineProps['modelClass']::getGroupedTopicsForModelIds(
			Zend_Registry::get('db'),
			$lineProps['modelId'],
			$dateRange,
			$this->getRequestSearchQuery(),
			$this->getRequestOrdering(),
			$this->getRequestLimit(),
			$this->getRequestOffset()
		);

		$tableData = array();
		foreach ($groupedTopics->data as $topic) {
			$tableData[] = array(
				'topic'=>$topic->text,
				'line_id'=> self::makeLineId($lineProps['modelClass'], $lineProps['modelId'], 'topic', $topic->normalised_topic),
				'normalised_topic'=>$topic->normalised_topic, 
				'mentions'=>$topic->mentions, 
				'polarity'=>round($topic->polarity, 3)
			);
		}

		if ($this->_request->format == 'csv') {
			$this->returnCsv($tableData, 'topics.csv');
		} else {
			$apiResult = array(
				'sEcho' => $this->_request->sEcho,
				'iTotalRecords' => $groupedTopics->count,
				'iTotalDisplayRecords' => $groupedTopics->count,
				'aaData' => $tableData
			);
			$this->apiSuccess($apiResult);
		}
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
			$statusType = $modelClass::getStatusType();
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