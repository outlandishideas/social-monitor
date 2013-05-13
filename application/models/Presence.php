<?php

class Model_Presence extends Model_Base {
	protected static $tableName = 'presences';
	protected static $sortColumn = 'handle';

	public static $bucketSizes = array(
		'bucket_half_hour' => 1800, // 30*60
		'bucket_4_hours' => 14400, // 4*60*60
		'bucket_12_hours' => 43200, // 12*60*60
		'bucket_day' => 86400 // 24*60*60
	);

	const TYPE_FACEBOOK = 'facebook';
	const TYPE_TWITTER = 'twitter';

	public static function fetchAllTwitter() {
		return self::fetchAll('type = :type', array(':type'=>self::TYPE_TWITTER));
	}

	public static function fetchAllFacebook() {
		return self::fetchAll('type = :type', array(':type'=>self::TYPE_FACEBOOK));
	}

	public function getLabel() {
		return $this->name ?: $this->handle;
	}

	/**
	 * @return Model_Country
	 */
	public function getCountry() {
		$country = null;
		$stmt = $this->_db->prepare('SELECT campaign_id FROM campaign_presences WHERE presence_id = :pid');
		$stmt->execute(array(':pid'=>$this->id));
		$campaignIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
		if ($campaignIds) {
			$countries = Model_Country::fetchAll('id IN (' . implode(',', $campaignIds) . ')');
			if ($countries) {
				$country = $countries[0];
			}
		}
		return $country;
	}

	/**
	 * Calculates the KPIs for this presence, based on the given start and end dates.
	 * If not given, calculates using the last month's worth of data
	 * @param null $startDate
	 * @param null $endDate
	 * @return array
	 */
	public function getKpiData($startDate = null, $endDate = null) {
		if (!isset($this->kpiData)) {
			$kpiData = array();

			if (!$startDate || !$endDate) {
				$endDate = new DateTime();
				$startDate = new DateTime();
				$startDate->sub(DateInterval::createFromDateString('1 month'));
			}

			$endDateString = $endDate->format('Y-m-d');
			$startDateString = $startDate->format('Y-m-d');

			$currentAudience = $this->popularity;
			$targetAudience = $this->getTargetAudience();
			$targetAudienceDate = $this->getTargetAudienceDate($startDateString, $endDateString);

			// target audience %
			$kpiData[Model_Campaign::KPI_POPULARITY_PERCENTAGE] = $targetAudience ? min(100, 100*$currentAudience/$targetAudience) : 100;

			// target audience rate (months until reaching target)
			if ($currentAudience >= $targetAudience) {
				$kpiData[Model_Campaign::KPI_POPULARITY_TIME] = 0; // already achieved
			} else if ($targetAudienceDate) {
				$diff = strtotime($targetAudienceDate) - $endDate->getTimestamp();
				$months = $diff/(60*60*24*365/12);
				$kpiData[Model_Campaign::KPI_POPULARITY_TIME] = $months;
			} else {
				$kpiData[Model_Campaign::KPI_POPULARITY_TIME] = null;
			}

			//posts per day
			$kpiData[Model_Campaign::KPI_POSTS_PER_DAY] = $this->getAveragePostsPerDay($startDateString, $endDateString);
			$this->kpiData = $kpiData;
		}

		return $this->kpiData;
	}

	public function updateInfo() {
		switch($this->type) {
			case self::TYPE_FACEBOOK:
				try {
					$data = Util_Facebook::pageInfo($this->handle);
				} catch (Exception_FacebookNotFound $e) {
					throw new Exception_FacebookNotFound('Facebook page not found: ' . $this->handle, $e->getCode(), $e->getFql(), $e->getErrors());
				}
				$this->uid = $data['page_id'];
				$this->image_url = $data['pic_square'];
				$this->name = $data['name'];
				$this->page_url = $data['page_url'];
				$this->popularity = $data['fan_count'];
				break;
			case self::TYPE_TWITTER:
				try {
					$data = Util_Twitter::userInfo($this->handle);
				} catch (Exception_TwitterNotFound $e) {
					throw new Exception_TwitterNotFound('Twitter user not found: ' . $this->handle, $e->getCode(), $e->getPath(), $e->getErrors());
				}
				$this->uid = $data->id_str;
				$this->image_url = $data->profile_image_url;
				$this->name = $data->name;
				$this->page_url = 'http://www.twitter.com/' . $data->screen_name;
				$this->popularity = $data->followers_count;
				break;
		}
	}

	public function getTypeLabel() {
		return ucfirst($this->type);
	}

	/**
	 * Fetches the posts/tweets for the presence, and inserts them into the database
	 * @return Util_FetchCount
	 * @throws Exception
	 */
	public function updateStatuses() {
		if (!$this->uid) {
			throw new Exception('Presence not initialised');
		}

		$toInsert = array();
		$tableName = null;
		$fetchCount = new Util_FetchCount(0, 0);
		switch($this->type) {
			case self::TYPE_FACEBOOK:
				$fetchCount->type = 'post';
				$tableName = 'facebook_stream';
				$stmt = $this->_db->prepare('SELECT created_time FROM facebook_stream WHERE presence_id = :id ORDER BY created_time DESC LIMIT 1');
				$stmt->execute(array(':id'=>$this->id));
				$since = $stmt->fetchColumn();
				if ($since) {
					$since = strtotime($since);
				}
				$posts = Util_Facebook::pagePosts($this->uid, $since);
				while ($posts) {
					$post = array_shift($posts);
					$toInsert[$post->post_id] = array(
						'post_id' => $post->post_id,
						'presence_id' => $this->id,
						'message' => $post->message,
						'created_time' => gmdate('Y-m-d H:i:s', $post->created_time),
						'actor_id' => $post->actor_id,
						'permalink' => $post->permalink,
						'type' => $post->type
					);
				}
				break;
			case self::TYPE_TWITTER:
				$fetchCount->type = 'tweet';
				$tableName = 'twitter_tweets';
				$stmt = $this->_db->prepare('SELECT tweet_id FROM twitter_tweets WHERE presence_id = :id ORDER BY created_time DESC LIMIT 1');
				$stmt->execute(array(':id'=>$this->id));
				$lastTweetId = $stmt->fetchColumn();
				$tweets = Util_Twitter::userTweets($this->uid, $lastTweetId);
				while ($tweets) {
					$tweet = array_shift($tweets);
					$parsedTweet = Util_Twitter::parseTweet($tweet);
					$toInsert[$tweet->id_str] = array(
						'tweet_id' => $tweet->id_str,
						'presence_id' => $this->id,
						'text_expanded' => $parsedTweet['text_expanded'],
						'created_time' => gmdate('Y-m-d H:i:s', strtotime($tweet->created_at)),
						'retweet_count' => $tweet->retweet_count,
						'html_tweet' => $parsedTweet['html_tweet'],
						'in_reply_to_user_uid' => $tweet->in_reply_to_user_id_str,
						'in_reply_to_status_uid' => $tweet->in_reply_to_status_id_str
					);
				}
				break;
		}

		if ($toInsert && $tableName) {
			$fetchCount->fetched += count($toInsert);
			$fetchCount->added += $this->insertData($tableName, $toInsert);
		}

		return $fetchCount;
	}

	/**
	 * Gets historical information about this presence
	 * @param string $type the type of data to get, eg 'popularity'
	 * @param $startDate
	 * @param $endDate
	 * @return array a series of (date, value) data points
	 */
	private function getHistoryData($type, $startDate = null, $endDate = null) {
		$clauses = array('presence_id = :id', 'type = :type');
		$args = array(':id'=>$this->id, ':type'=>$type);
		if ($startDate) {
			$clauses[] = 'datetime >= :start_date';
			$args[':start_date'] = $startDate;
		}
		if ($endDate) {
			$clauses[] = 'datetime <= :end_date';
			$args[':end_date'] = $endDate;
		}
		$sql = 'SELECT datetime, value
			FROM presence_history
			WHERE ' . implode(' AND ', $clauses) . '
			ORDER BY datetime ASC';
		$stmt = $this->_db->prepare($sql);
		$stmt->execute($args);
		$data = $stmt->fetchAll(PDO::FETCH_OBJ);
		return $data;
	}

	public function getPopularityData($startDate, $endDate){
		return $this->getHistoryData('popularity', $startDate, $endDate);
	}

	public function getStatuses($startDate, $endDate, $search, $order, $limit, $offset){
		$tableName = $this->type == self::TYPE_TWITTER ? 'twitter_tweets' : 'facebook_stream';
		$clauses = array(
			'presence_id = :pid',
			'created_time >= :start_date',
			'created_time <= :end_date'
		);
		$args = array(
			':pid'=>$this->id,
			':start_date'=>$startDate,
			':end_date'=>$endDate
		);

		if ($search) {
			//parse special search filters
			$searchBits = explode(':', $search, 2);
			if (count($searchBits) > 1 && $searchBits[0] == 'date') {
				$dates = explode('/', $searchBits[1]);
				$clauses[] = 'created_time >= :filter_min_date';
				$clauses[] = 'created_time < :filter_max_date';
				$args[':filter_min_date'] = gmdate('Y-m-d H:i:s', strtotime($dates[0]));
				$args[':filter_max_date'] = gmdate('Y-m-d H:i:s', strtotime($dates[1]));
			} else {
				$textMatchColumn = ($this->type == self::TYPE_TWITTER ? 'text_expanded' : 'message');
				$textMatches = array("$textMatchColumn LIKE :search");
				if ($this->type == self::TYPE_TWITTER) {
					$textMatches[] = 'user.name LIKE :search';
					$textMatches[] = 'user.screen_name LIKE :search';
				} else {
					$textMatches[] = 'actor.name LIKE :search';
				}
				$clauses[] = '(' . implode(' OR ', $textMatches) . ')';
				$args[':search'] = "%$search%";
			}
		}

		// todo: other sort columns?
		$ordering = array();
		foreach ($order as $column=>$dir) {
			switch ($column) {
				case 'date':
					$column = 'created_time';
					break;
				default:
					$column = 'created_time';
					break;
			}
			$ordering[] = $column . ' ' . $dir;
		}

		$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM $tableName WHERE " . implode (' AND ', $clauses) . ' ORDER BY ' . implode(',', $ordering);
		if ($limit != -1) {
			$sql .= ' LIMIT '.$limit;
		}
		if ($offset != -1) {
			$sql .= ' OFFSET '.$offset;
		}

		$stmt = $this->_db->prepare($sql);
		$stmt->execute($args);
		$statuses = $stmt->fetchAll(PDO::FETCH_OBJ);
		$total = $this->_db->query('SELECT FOUND_ROWS()')->fetch(PDO::FETCH_COLUMN);
		if($this->type == self::TYPE_FACEBOOK){
			$actorIds = array_map(function($a) { return $a->actor_id; }, $statuses);
			$actors = $this->getActors($actorIds);
			foreach($statuses as $status){
				if (array_key_exists($status->actor_id, $actors)) {
					$status->actor = $actors[$status->actor_id];
				} else {
					$status->actor = (object)array(
						'id'=>$status->actor_id,
						'username'=>null,
						'name'=>null,
						'pic_url'=>null,
						'profile_url'=>null,
						'type'=>'unknown',
						'last_fetched'=>null
					);
				}
			}
		}
		return (object)array('statuses'=>$statuses, 'total'=>$total);
	}

	/**
	 * Gets a list of facebook actors, keyed by their ID
	 * @param array $actorIds
	 * @return array
	 */
	public function getActors($actorIds = array()){
		$actors = array();
		if ($actorIds) {
			$actorIds = implode(',', array_unique($actorIds));
			$stmt = $this->_db->prepare("SELECT * FROM facebook_actors WHERE id IN ( $actorIds )");
			$stmt->execute();
			foreach($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
				$actors[$row->id] = $row;
			}
		}
		return $actors;
	}

	public function getAveragePostsPerDay($startDate, $endDate) {
		$tableName = $this->type == self::TYPE_TWITTER ? 'twitter_tweets' : 'facebook_stream';
		$stmt = $this->_db->prepare("SELECT COUNT(1)/DATEDIFF(:end_date, :start_date) AS av FROM $tableName WHERE presence_id = :pid AND created_time >= :start_date AND created_time <= :end_date");
		$stmt->execute(array(':pid'=>$this->id, ':start_date'=>$startDate, ':end_date'=>$endDate));
		return floatval($stmt->fetchColumn());
	}

	public function getPostsPerDayData($startDate, $endDate) {
		$tableName = $this->type == self::TYPE_TWITTER ? 'twitter_tweets' : 'facebook_stream';
		$stmt = $this->_db->prepare(
			"SELECT date, COUNT(date) AS post_count FROM
			(SELECT DATE(created_time) AS date FROM $tableName WHERE presence_id = :pid AND created_time >= :start_date AND created_time <= :end_date) AS tmp
			GROUP BY date"
		);
		$stmt->execute(array(':pid'=>$this->id, ':start_date'=>$startDate, ':end_date'=>$endDate));
		$counts = array();
		$date = gmdate('Y-m-d', strtotime($startDate));
		while ($date <= $endDate) {
			$counts[$date] = (object)array('date'=>$date, 'post_count'=>0);
			$date = gmdate('Y-m-d', strtotime($date . '+1 day'));
		}
		foreach($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
			$counts[$row->date] = $row;
		}
		return $counts;
	}

//	public function getRecentPopularityData(){
//		$stmt = $this->_db->prepare('SELECT popularity, handle
//			FROM presences
//			WHERE presence_id = :id');
//		$stmt->execute(array(':id'=>$this->id));
//		return $stmt->fetchAll(PDO::FETCH_OBJ);
//	}

	public function getTargetAudience() {
		$target = 0;
		$country = $this->getCountry();
		if ($country) {
			$target = $country->getTargetAudience();
			$target *= BaseController::getOption($this->type == self::TYPE_FACEBOOK ? 'fb_min' : 'tw_min');
			$target /= 100;
			$target = round($target);
		}
		return $target;
	}

	/**
	 * Gets the date at which the target audience size will be reached, based on the trend over the given time period.
	 * If the target is already reached, or there is no target, this will return null.
	 * If any of these conditions are met, this will return the maximum date possible:
	 * - popularity has never varied
	 * - the calculated date is in the past
	 * - there are fewer than 2 data points
	 * - the calculated date would be too far in the future (32-bit date problem)
	 * @param $startDate
	 * @param $endDate
	 * @return null|string
	 */
	public function getTargetAudienceDate($startDate, $endDate) {
		$date = null;
		$target = $this->getTargetAudience();
		if ($target > 0 && $this->popularity < $target) {
			$data = $this->getPopularityData($startDate, $endDate);
			$n = count($data);
			if ($n > 1) {
				// calculate line of best fit (see http://www.endmemo.com/statistics/lr.php)
				$meanX = $meanY = $sumXY = $sumXX = 0;
				foreach ($data as $row) {
					$row->datetime = strtotime($row->datetime);
					$meanX += $row->datetime;
					$meanY += $row->value;
					$sumXY += $row->datetime*$row->value;
					$sumXX += $row->datetime*$row->datetime;
				}
				$meanX /= $n;
				$meanY /= $n;
				$a = ($sumXY - $n*$meanX*$meanY)/($sumXX - $n*$meanX*$meanX);
				$b = $meanY - $a*$meanX;
				if ($a > 0) {
					$timestamp = ($target - $b)/$a;
					if ($timestamp < PHP_INT_MAX) {
						$date = date('Y-m-d', $timestamp);
					}
				}
			}

			if (!$date || $date < date('Y-m-d')) {
				$date = date('Y-m-d', PHP_INT_MAX);
			}
		}
		return $date;
	}

	/**
	 * Delete all of the presence's associated data
	 */
	public function delete() {
		$this->_db->prepare('DELETE FROM campaign_presences WHERE presence_id = :pid')->execute(array(':pid'=>$this->id));
		$this->_db->prepare('DELETE FROM presence_history WHERE presence_id = :pid')->execute(array(':pid'=>$this->id));
		switch($this->type) {
			case self::TYPE_FACEBOOK:
				$this->_db->prepare('DELETE FROM facebook_stream WHERE presence_id = :pid')->execute(array(':pid'=>$this->id));
				break;
			case self::TYPE_TWITTER:
				$this->_db->prepare('DELETE FROM twitter_tweets WHERE presence_id = :pid')->execute(array(':pid'=>$this->id));
				break;
		}
		parent::delete();
	}

}