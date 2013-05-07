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
		$kpis = array();

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
		$kpis[Model_Campaign::KPI_POPULARITY_PERCENTAGE] = $targetAudience ? min(100, 100*$currentAudience/$targetAudience) : 100;

		// target audience rate (months until reaching target)
		if ($currentAudience >= $targetAudience) {
			$kpis[Model_Campaign::KPI_POPULARITY_TIME] = 0; // already achieved
		} else {
			if ($targetAudienceDate) {
				$diff = $endDate->diff(new DateTime($targetAudienceDate));
				$months = $diff->m + 12*$diff->y;
				$kpis[Model_Campaign::KPI_POPULARITY_TIME] = $months;
			}
		}

		//posts per day
		$kpis[Model_Campaign::KPI_POSTS_PER_DAY] = $this->getAveragePostsPerDay($startDateString, $endDateString);

		return $kpis;
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

    public function getStatuses($startDate, $endDate){
        $tableName = $this->type == self::TYPE_TWITTER ? 'twitter_tweets' : 'facebook_stream';
        $stmt = $this->_db->prepare(
            "SELECT * FROM $tableName WHERE presence_id = :pid AND created_time >= :start_date AND created_time <= :end_date"
        );
        $stmt->execute(array(':pid'=>$this->id, ':start_date'=>$startDate, ':end_date'=>$endDate));
        if($this->type == self::TYPE_TWITTER){
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } else {
            return $stmt->fetchAll(PDO::FETCH_OBJ);
            /*
            $actorIds = array();
            foreach($stmt->fetchAll(PDO::FETCH_OBJ) as $row){
                $actorIds[$row->actor_id] = $row->actor_id;
            }
            $actors = $this->getActors($actorIds);
            $return = array();
            foreach($stmt->fetchAll(PDO::FETCH_OBJ) as $row){
                $row->actor = $actors[$row->actor_id];
                $return[] = $row + $actors[$row->actor_id];
            }
            return $return;
            */
        }

    }

    public function getActors($actorIds = array()){
        $actorIds = implode(',', $actorIds);
        $stmt = $this->_db->prepare(
            "SELECT * FROM facebook_actors WHERE actor_id IN ( $actorIds )"
        );
        $stmt->execute();
        $return = array();
        foreach($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
            $return[$row->actor_id] = $row;
        }
        return $return;
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

//    public function getRecentPopularityData(){
//        $stmt = $this->_db->prepare('SELECT popularity, handle
//			FROM presences
//			WHERE presence_id = :id');
//        $stmt->execute(array(':id'=>$this->id));
//        return $stmt->fetchAll(PDO::FETCH_OBJ);
//    }

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
	 * Gets the date at which the target audience size will be reached, based on the trend over the given time period. The date may be in the past
	 * If any of these conditions are met, this will return null:
	 * - no target audience size
	 * - popularity has never varied
	 * - the calculated date is in the past
	 * If there are fewer than 2 data points, or the calculated date would be too far in the future (32-bit date problem), this will return the maximum date possible
	 * @param $startDate
	 * @param $endDate
	 * @return null|string
	 */
	public function getTargetAudienceDate($startDate, $endDate) {
		// todo: change this to throw exceptions for the different reasons listed above
		$date = null;
		$target = $this->getTargetAudience();
		if ($target > 0) {
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
					if (!$date || $date < date('Y-m-d')) {
						$date = date('Y-m-d', PHP_INT_MAX);
					}
				}
			} else {
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