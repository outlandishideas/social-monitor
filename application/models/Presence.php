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

    public function graphs() {
        $graphs = array();
        $graphs[] = (object)array(
            'metric' => 'popularity',
            'yAxisLabel' => ($this->isForFacebook() ? 'Fans' : 'Followers') . ' gained per day',
            'title' => 'Audience Rate'
        );
        $graphs[] = (object)array(
            'metric' => 'posts_per_day',
            'yAxisLabel' => 'Posts per day',
            'title' => 'Posts Per Day'
        );
        $graphs[] = (object)array(
            'metric' => 'response_time',
            'yAxisLabel' => 'Response time (hours)',
            'title' => 'Average Response Time (hours)'
        );
        foreach ($graphs as $g) {
            $g->presence_id = $this->id;
        }
        return $graphs;
    }

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

	public function isForTwitter() {
		return $this->type == self::TYPE_TWITTER;
	}

	public function isForFacebook() {
		return $this->type == self::TYPE_FACEBOOK;
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

			//response time
			$kpiData[Model_Campaign::KPI_RESPONSE_TIME] = $this->getAverageResponseTime($startDateString, $endDateString);

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

		$statuses = array();
		$responses = array();
		$links = array();
		$failedLinks = array();
		$tableName = null;
		$fetchCount = new Util_FetchCount(0, 0);
		switch($this->type) {
			case self::TYPE_FACEBOOK:
				$fetchCount->type = 'post';
				$tableName = 'facebook_stream';
				$stmt = $this->_db->prepare("SELECT created_time FROM $tableName WHERE presence_id = :id ORDER BY created_time DESC LIMIT 1");
				$stmt->execute(array(':id'=>$this->id));
				$since = $stmt->fetchColumn();
				if ($since) {
					$since = strtotime($since);
				}
				$posts = Util_Facebook::pagePosts($this->uid, $since);
				while ($posts) {
					$post = array_shift($posts);
					$postedByOwner = $post->actor_id == $this->uid;
					if ($postedByOwner) {
						$newLinks = $this->extractLinks($post->message);
						foreach ($newLinks as $link) {
							$link['post_id'] = $post->post_id;
							$link['type'] = $this->type;
							$links[] = $link;
						}
					}
					$statuses[$post->post_id] = array(
						'post_id' => $post->post_id,
						'presence_id' => $this->id,
						'message' => $post->message,
						'created_time' => gmdate('Y-m-d H:i:s', $post->created_time),
						'actor_id' => $post->actor_id,
						'permalink' => $post->permalink,
						'type' => $post->type,
						'posted_by_owner' => $postedByOwner,
						'needs_response' => !$postedByOwner && $post->message
					);
				}

				// update the responses for any non-page posts that don't have a response yet.
				// Only get those that explicitly need one, or were posted in the last 3 days
				$necessarySince = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' -30 days'));
				$unnecessarySince = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' -3 days'));
				$stmt = $this->_db->prepare("SELECT DISTINCT a.post_id
					FROM (
						SELECT * FROM $tableName
						WHERE presence_id = :id
						AND in_response_to IS NULL
						AND (
							(needs_response = 1 AND created_time > :necessary_since) OR
							(posted_by_owner = 0 AND message <> '' AND message IS NOT NULL AND created_time > :unnecessary_since)
						)
					) as a
					LEFT OUTER JOIN $tableName AS b
						ON b.presence_id = a.presence_id
						AND b.in_response_to = a.post_id
					WHERE b.id IS NULL");
				$stmt->execute(array(':id'=>$this->id, ':necessary_since'=>$necessarySince, ':unnecessary_since'=>$unnecessarySince));
				$postIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
				$newResponses = Util_Facebook::responses($this->uid, $postIds);
				foreach ($newResponses as $response) {
					$responses[$response->id] = array(
						'post_id' => $response->id,
						'presence_id' => $this->id,
						'message' => $response->text,
						'created_time' => gmdate('Y-m-d H:i:s', $response->time),
						'actor_id' => $response->fromid,
						'posted_by_owner' => true,
						'in_response_to' => $response->post_id
					);
				}
				break;
			case self::TYPE_TWITTER:
				$fetchCount->type = 'tweet';
				$tableName = 'twitter_tweets';
				$stmt = $this->_db->prepare("SELECT tweet_id FROM $tableName WHERE presence_id = :id ORDER BY created_time DESC LIMIT 1");
				$stmt->execute(array(':id'=>$this->id));
				$lastTweetId = $stmt->fetchColumn();
				$tweets = Util_Twitter::userTweets($this->uid, $lastTweetId);
				while ($tweets) {
					$tweet = array_shift($tweets);
					$parsedTweet = Util_Twitter::parseTweet($tweet);
					$statuses[$tweet->id_str] = array(
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

		if ($tableName) {
			if ($statuses) {
				$fetchCount->fetched += count($statuses);
				$fetchCount->added += $this->insertData($tableName, $statuses);
			}
			if ($responses) {
				$this->insertData($tableName, $responses);
			}
			if ($links) {
				$columnName = $this->isForFacebook() ? 'post_id' : 'tweet_id';
				$postIds = array_map(function($a) use($columnName) { return "'" . $a[$columnName] . "'"; }, $links);
				$postIds = implode(',', $postIds);
				$stmt = $this->_db->prepare("SELECT post_id, id FROM $tableName WHERE presence_id = :id AND $columnName IN ($postIds)");
				$stmt->execute(array(':id'=>$this->id));
				$lookup = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
				foreach ($links as $i=>$link) {
					if (array_key_exists($link[$columnName], $lookup)) {
						$links[$i]['status_id'] = $lookup[$link[$columnName]];
						unset($links[$i][$columnName]);
					}
				}
				$this->insertData('status_links', $links);
			}
		}

		return $fetchCount;
	}

	private function extractLinks($message) {
		$links = array();
		$failedLinks = array();
		if (preg_match_all('/[^\s]{5,}/', $message, $tokens)) {
			foreach ($tokens[0] as $token) {
				$token = trim($token, '.,');
				if (filter_var($token, FILTER_VALIDATE_URL)) {
					try {
						$url = Util_Http::resolveUrl($token);
						$start = max(strpos($url, '//')+2, 0);
						$domain = substr($url, $start);
						$end = strpos($domain, '/');
						if ($end > 0) {
							$domain = substr($domain, 0, $end);
						}
						$links[] = array(
							'url'=>$url,
							'domain'=>$domain
						);
					} catch (RuntimeException $ex) {
						// ignore failed URLs
						$failedLinks[] = $token;
					}
				}
			}
		}
		return $links;
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

		if ($this->isForTwitter()) {
			$tableName = 'twitter_tweets';
		} else {
			$tableName = 'facebook_stream';
			$clauses[] = 'in_response_to IS NULL';
		}

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
				$textMatchColumn = ($this->isForTwitter() ? 'text_expanded' : 'message');
				$textMatches = array("$textMatchColumn LIKE :search");
				if ($this->isForTwitter()) {
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

		$sql = "SELECT SQL_CALC_FOUND_ROWS s.* FROM $tableName AS s WHERE " . implode (' AND ', $clauses) . ' ORDER BY ' . implode(',', $ordering);
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
		if($this->isForFacebook()){
			$actorIds = array_map(function($a) { return $a->actor_id; }, $statuses);
			$actors = $this->getActors($actorIds);

			$postIds = array_map(function($a) { return $a->post_id; }, $statuses);
			$responses = array();
			if ($postIds) {
				$postIds = array_map(function($a) { return "'" . $a . "'"; }, $postIds);
				$postIds = implode(',', $postIds);
				$stmt = $this->_db->prepare("SELECT * FROM facebook_stream WHERE presence_id = :pid AND in_response_to IN ($postIds)");
				$stmt->execute(array(':pid'=>$this->id));
				foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $response) {
					$key = $response->in_response_to;
					if (!array_key_exists($key, $responses) || ($response->created_time < $responses[$key]->created_time)) {
						$responses[$key] = $response;
					}
				}
			}

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

				if (array_key_exists($status->post_id, $responses)) {
					$status->first_response = $responses[$status->post_id];
				} else {
					$status->first_response = null;
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
		$clauses = array(
			'presence_id = :pid',
			'created_time >= :start_date',
			'created_time <= :end_date'
		);
		if ($this->isForTwitter()) {
			$tableName = 'twitter_tweets';
		} else {
			$tableName = 'facebook_stream';
			$clauses[] = 'posted_by_owner = 1';
			$clauses[] = 'in_response_to IS NULL';
		}

		$sql = 'SELECT COUNT(1)/DATEDIFF(:end_date, :start_date) AS av FROM ' . $tableName . ' WHERE ' . implode(' AND ', $clauses);
		$stmt = $this->_db->prepare($sql);
		$stmt->execute(array(':pid'=>$this->id, ':start_date'=>$startDate, ':end_date'=>$endDate));
		return floatval($stmt->fetchColumn());
	}

	public function getAverageResponseTime($startDate, $endDate) {
		$data = $this->getResponseData($startDate, $endDate);
		$maxTime = floatval(BaseController::getOption('response_time_bad'));
		$now = time();
		$totalTime = 0;
		if ($data) {
			foreach ($data as $row) {
				$diff = ($row->first_response ? strtotime($row->first_response->created_time) : $now) - strtotime($row->post->created_time);
				$diff /= (60*60);
				$diff = min($maxTime, $diff);
				$totalTime += $diff;
			}
			return $totalTime/count($data);
		} else {
			return 0;
		}
	}

	public function getPostsPerDayData($startDate, $endDate) {
		$clauses = array(
			'presence_id = :pid',
			'created_time >= :start_date',
			'created_time <= :end_date'
		);
		if ($this->isForTwitter()) {
			$tableName = 'twitter_tweets';
		} else {
			$tableName = 'facebook_stream';
			$clauses[] = 'posted_by_owner = 1';
			$clauses[] = 'in_response_to IS NULL';
		}

		$sql = 'SELECT date, COUNT(date) AS post_count
			FROM (
				SELECT DATE(created_time) AS date
				FROM ' . $tableName . '
				WHERE ' . implode(' AND ', $clauses) . '
			) AS tmp GROUP BY date';
		$stmt = $this->_db->prepare($sql);
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

	public function getResponseData($startDate, $endDate) {
		$responseData = array();
		if ($this->isForFacebook()) {
			$clauses = array(
				'presence_id = :pid',
				'created_time >= :start_date',
				'created_time <= :end_date',
				'posted_by_owner = 0',
				"(in_response_to IS NULL OR in_response_to = '')"
			);
			$args = array(':pid'=>$this->id, ':start_date'=>$startDate, ':end_date'=>$endDate);
			$stmt = $this->_db->prepare('SELECT * FROM facebook_stream WHERE ' . implode(' AND ', $clauses) . ' ORDER BY created_time DESC');
			$stmt->execute($args);
			foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $p) {
				$responseData[$p->post_id] = (object)array(
					'post'=>$p,
					'first_response'=>null
				);
			}

			if ($responseData) {
				// now get the responses
				$postIds = array_map(function($a) { return "'" . $a . "'"; }, array_keys($responseData));
				$stmt = $this->_db->prepare('SELECT * FROM facebook_stream WHERE presence_id = :pid AND in_response_to IN (' . implode(',', $postIds) . ')');
				$stmt->execute(array(':pid'=>$this->id));
				foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $r) {
					$key = $r->in_response_to;
					if (!$responseData[$key]->first_response || $r->created_time < $responseData[$key]->first_response->created_time) {
						$responseData[$key]->first_response = $r;
					}
				}
			}

			foreach ($responseData as $i=>$row) {
				if (!$row->post->needs_response && !$row->first_response) {
					unset($responseData[$i]);
				}
			}
		}
		return $responseData;
	}

	public function getTargetAudience() {
		$target = 0;
		$country = $this->getCountry();
		if ($country) {
			$target = $country->getTargetAudience();
			$target *= BaseController::getOption($this->isForFacebook() ? 'fb_min' : 'tw_min');
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