<?php

class Model_Presence extends Model_Base {
	protected static $tableName = 'presences';
	protected static $sortColumn = 'handle';

    const ICON_TYPE = 'icon-bar-chart';

    const METRIC_POPULARITY_PERCENT = 'popularity';
	const METRIC_POPULARITY_TIME = 'popularity_time';
	const METRIC_POPULARITY_RATE = 'popularity_rate';
	const METRIC_POSTS_PER_DAY = 'posts_per_day';
	const METRIC_RESPONSE_TIME = 'response_time';
    const METRIC_RATIO_REPLIES_TO_OTHERS_POSTS = 'replies_to_posts';
    const METRIC_LINKS_PER_DAY = 'links_per_day';
    const METRIC_LIKES_PER_POST = 'likes_per_post';

    //Badge Metrics
    const METRIC_BADGE_TOTAL = 'total';
    const METRIC_BADGE_REACH = 'reach';
    const METRIC_BADGE_ENGAGEMENT = 'engagement';
    const METRIC_BADGE_QUALITY = 'quality';

	public static $ALL_METRICS = array(
		self::METRIC_POPULARITY_PERCENT,
		self::METRIC_POPULARITY_TIME,
		self::METRIC_POPULARITY_RATE,
		self::METRIC_POSTS_PER_DAY,
		self::METRIC_RESPONSE_TIME,
	);

    public static function ALL_BADGES() {
        return array(
            self::METRIC_BADGE_REACH => self::$METRIC_REACH,
            self::METRIC_BADGE_ENGAGEMENT => self::$METRIC_ENGAGEMENT,
            self::METRIC_BADGE_QUALITY => self::$METRIC_QUALITY
        );
    }


    public static $METRIC_QUALITY = array(
        self::METRIC_POSTS_PER_DAY,
        self::METRIC_LINKS_PER_DAY,
        self::METRIC_LIKES_PER_POST
    );

    public static $METRIC_ENGAGEMENT = array(
        self::METRIC_RATIO_REPLIES_TO_OTHERS_POSTS,
		self::METRIC_RESPONSE_TIME,
        self::METRIC_POPULARITY_PERCENT
    );

    public static $METRIC_REACH = array(
        self::METRIC_POPULARITY_PERCENT,
        self::METRIC_POPULARITY_TIME,
        self::METRIC_RATIO_REPLIES_TO_OTHERS_POSTS
    );

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

    public function presenceIcon($append =  null){
        switch($this->type){
            case self::TYPE_FACEBOOK:
                return 'icon-facebook'.$append;
                break;
            case self::TYPE_TWITTER:
                return 'icon-twitter'.$append;
                break;
            default:
                return false;
        }
    }

    public function getPresenceIcon($classes = array()){

        $icon = $this->presenceIcon();

        if(!$icon) return false;

        $classes[] = $icon;

        $classes = implode(' ',$classes);

        return $classes;

    }

    public function getLargePresenceIcon($classes = array()) {

        $defaults = array('icon-large');

        $classes = $defaults + $classes;

        return $this->getPresenceIcon($classes);
    }

    public function getPresenceSign($classes = array()) {

        $icon = $this->presenceIcon('-sign');

        if(!$icon) return false;

        $classes[] = $icon;

        $classes = implode(' ',$classes);

        return $classes;
    }

    public function getLargePresenceSign($classes = array()) {

        $defaults = array('icon-large');

        $classes = $defaults + $classes;

        return $this->getPresenceSign($classes);
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
	 * @param bool $useCache
	 * @return array
	 */
	public function getKpiData($startDate = null, $endDate = null, $useCache = true) {
		if (!isset($this->kpiData)) {
			$kpiData = array();

			if (!$startDate || !$endDate) {
				$endDate = new DateTime();
				$startDate = new DateTime();
				$startDate->sub(DateInterval::createFromDateString('1 month'));
			}

			$endDateString = $endDate->format('Y-m-d');
			$startDateString = $startDate->format('Y-m-d');

			if ($useCache) {
				$stmt = $this->_db->prepare('SELECT metric, value FROM kpi_cache WHERE presence_id = :pid AND start_date = :start AND end_date = :end');
				$stmt->execute(array(':pid'=>$this->id, ':start'=>$startDateString, ':end'=>$endDateString));
				$cachedValues = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
			} else {
				$cachedValues = array();
			}

			if (array_key_exists(self::METRIC_POPULARITY_PERCENT, $cachedValues)) {
				$kpiData[self::METRIC_POPULARITY_PERCENT] = $cachedValues[self::METRIC_POPULARITY_PERCENT];
				$kpiData[self::METRIC_POPULARITY_TIME] = $cachedValues[self::METRIC_POPULARITY_TIME];
			} else {
				$currentAudience = $this->popularity;
				$targetAudience = $this->getTargetAudience();
				$targetAudienceDate = $this->getTargetAudienceDate($startDateString, $endDateString);

				// target audience %
				$kpiData[self::METRIC_POPULARITY_PERCENT] = $targetAudience ? min(100, 100*$currentAudience/$targetAudience) : 100;

				// target audience rate (months until reaching target)
				if ($currentAudience >= $targetAudience) {
					$kpiData[self::METRIC_POPULARITY_TIME] = 0; // already achieved
				} else if ($targetAudienceDate) {
					$diff = strtotime($targetAudienceDate) - $endDate->getTimestamp();
					$months = $diff/(60*60*24*365/12);
					$kpiData[self::METRIC_POPULARITY_TIME] = $months;
				} else {
					$kpiData[self::METRIC_POPULARITY_TIME] = null;
				}
			}

			//posts per day
			$metric = self::METRIC_POSTS_PER_DAY;
			if (array_key_exists($metric, $cachedValues)) {
				$kpiData[$metric] = $cachedValues[$metric];
			} else {
				$kpiData[$metric] = $this->getAveragePostsPerDay($startDateString, $endDateString);
			}

			//response time
			$metric = self::METRIC_RESPONSE_TIME;
			if (array_key_exists($metric, $cachedValues)) {
				$kpiData[$metric] = $cachedValues[$metric];
			} else {
				$kpiData[$metric] = $this->getAverageResponseTime($startDateString, $endDateString);
			}

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
							$link['external_id'] = $post->post_id;
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
					foreach ($tweet->entities->urls as $urlInfo) {
						try {
							$url = Util_Http::resolveUrl($urlInfo->expanded_url);
							$domain = $this->extractDomain($url);
							$links[] = array(
								'url'=>$url,
								'domain'=>$domain,
								'external_id'=>$tweet->id_str,
								'type'=>$this->type
							);
						} catch (Exception $ex) { }
					}
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
				$postIds = array_map(function($a) { return "'" . $a['external_id'] . "'"; }, $links);
				$postIds = implode(',', $postIds);
				$columnName = $this->isForFacebook() ? 'post_id' : 'tweet_id';
				$stmt = $this->_db->prepare("SELECT $columnName, id FROM $tableName WHERE presence_id = :id AND $columnName IN ($postIds)");
				$stmt->execute(array(':id'=>$this->id));
				$lookup = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
				foreach ($links as $i=>$link) {
					if (array_key_exists($link['external_id'], $lookup)) {
						$links[$i]['status_id'] = $lookup[$link['external_id']];
						unset($links[$i]['external_id']);
					}
				}
				$this->insertData('status_links', $links);
			}
		}

		return $fetchCount;
	}

	private function extractDomain($url) {
		$start = max(strpos($url, '//')+2, 0);
		$domain = substr($url, $start);
		$end = strpos($domain, '/');
		if ($end > 0) {
			$domain = substr($domain, 0, $end);
		}
		return $domain;
	}

	private function extractLinks($message) {
		$links = array();
		$failedLinks = array();
		if (preg_match_all('/[^\s]{5,}/', $message, $tokens)) {
			foreach ($tokens[0] as $token) {
				$token = trim($token, '.,;!"()');
				if (filter_var($token, FILTER_VALIDATE_URL)) {
					try {
						$url = Util_Http::resolveUrl($token);
						$domain = $this->extractDomain($url);
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

    public function getTotalData($startDate, $endDate){
        return $this->getHistoryData('total', $startDate, $endDate);
    }

    public function getReachData($startDate, $endDate){
        return $this->getHistoryData('reach', $startDate, $endDate);
    }

    public function getEngagementData($startDate, $endDate){
        return $this->getHistoryData('engagement', $startDate, $endDate);
    }

    public function getQualityData($startDate, $endDate){
        return $this->getHistoryData('quality', $startDate, $endDate);
    }

    public function getTotalRankingData($startDate, $endDate){
        return $this->getHistoryData('total_ranking', $startDate, $endDate);
    }

    public function getReachRankingData($startDate, $endDate){
        return $this->getHistoryData('reach_ranking', $startDate, $endDate);
    }

    public function getEngagementRankingData($startDate, $endDate){
        return $this->getHistoryData('engagement_ranking', $startDate, $endDate);
    }

    public function getQualityRankingData($startDate, $endDate){
        return $this->getHistoryData('quality_ranking', $startDate, $endDate);
    }

    public function calculateMetrics($badgeType, $metrics = array())
    {

        $endDate = new DateTime();
        $startDate = new DateTime();
        $startDate->sub(DateInterval::createFromDateString('1 month'));

        $end = $endDate->format('Y-m-d');
        $start = $startDate->format('Y-m-d');

        $dateString = $endDate->format('Y-m-d H-i-s');

        $dataRow = (object)array(
            'presence_id' => $this->id,
            'value' => 0,
            'datetime' => $dateString,
            'type' => $badgeType
        );

        if(empty($metrics))
        {
            $badges = Model_Presence::ALL_BADGES();
            $metrics = $badges[$badgeType];
        }

        foreach($metrics as $metric){

            switch($metric){

                case(Model_Presence::METRIC_POSTS_PER_DAY):

                    $target = BaseController::getOption('updates_per_day');
                    $actual = $this->getAveragePostsPerDay($start, $end);

                    if(!$target){
                        $percent = 0;
                    } else if($actual > $target){
                        $percent = 100;
                    } else {
                        $percent = ( $actual / $target ) * 100;
                    }

                    $title = 'Average Posts Per Day';

                    break;

                case(Model_Presence::METRIC_LINKS_PER_DAY):

                    $target = BaseController::getOption('links_per_day');
                    $actual = $this->getAverageLinksPerDay($start, $end);

                    if(!$target){
                        $percent = 0;
                    } else if($actual > $target){
                        $percent = 100;
                    } else {
                        $percent = ( $actual / $target ) * 100;
                    }

                    $title = 'Average Links Per Day';

                    break;

                case(Model_Presence::METRIC_LIKES_PER_POST):

                    $target = BaseController::getOption('likes_per_post_best');
                    $actual = $this->getAverageLikesPerPost($start, $end);

                    if(!$target){
                        $percent = 0;
                    } else if($actual > $target){
                        $percent = 100;
                    } else {
                        $percent = ( $actual / $target ) * 100;
                    }

                    $title = 'Average Likes Per Post';

                    break;

                case(Model_Presence::METRIC_RESPONSE_TIME):

                    $target = BaseController::getOption('updates_per_day');
                    $actual = $this->getAverageResponseTime($start, $end);

                    if(!$target){
                        $percent = 0;
                    } else if($actual > $target){
                        $percent = ( $target / $actual ) * 100;
                    } else {
                        $percent = 100;
                    }

                    $title = 'Average Response Time';

                    break;

                case(Model_Presence::METRIC_RATIO_REPLIES_TO_OTHERS_POSTS):

                    $target = BaseController::getOption('replies_to_number_posts_best');
                    $actual = $this->getRatioRepliesToOthersPosts($start, $end);

                    if(!$target){
                        $percent = 0;
                    } else if($actual > $target){
                        $percent = 100;
                    } else {
                        $percent = ( $actual / $target ) * 100;
                    }

                    $title = 'Ratio of Replies to Posts from others';

                    break;

                default:

                    $title = 'Default';
                    $target = 0;
                    $actual = 0;
                    $percent = 0;

            }

            $dataRow->value += $percent;

        }

        $dataRow->value /= count($metrics);

        return $dataRow;
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
			$links = array();
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

				$ids = array_map(function($a) { return $a->id; }, $statuses);
				$ids = implode(',', $ids);
				$stmt = $this->_db->prepare("SELECT * FROM status_links AS l INNER JOIN domains AS d USING (domain) WHERE status_id IN ($ids) AND type = :type");
				$stmt->execute(array(':type'=>$this->type));
				foreach($stmt->fetchAll(PDO::FETCH_OBJ) as $link) {
					$key = $link->status_id;
					if (!array_key_exists($key, $links)) {
						$links[$key] = array();
					}
					$links[$key][] = $link;
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

				if (array_key_exists($status->id, $links) && $status->actor_id == $this->uid) {
					$status->links = $links[$status->id];
					usort($status->links, function($a, $b) { return $b->is_bc - $a->is_bc; });
				} else {
					$status->links = null;
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

    public function getAverageLinksPerDay($startDate, $endDate) {

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

        $sql = '
        SELECT SUM(links.count) AS links, COUNT(statuses.id) AS posts, SUM(links.count)/COUNT(statuses.id) as av
        FROM ' . $tableName . ' AS statuses
        lEFT JOIN (
            SELECT status_id, COUNT(url) as count FROM `status_links` GROUP BY status_id
        ) AS links ON statuses.id = links.status_id
        WHERE ' . implode(' AND ', $clauses);
        $stmt = $this->_db->prepare($sql);
        $stmt->execute(array(':pid'=>$this->id, ':start_date'=>$startDate, ':end_date'=>$endDate));
        return floatval($stmt->fetchColumn());
    }

    /*
     * function to average number of likes per post
     * */
    public function getAverageLikesPerPost($startDate, $endDate){
        $clauses = array(
            'presence_id = :pid',
            'created_time >= :start_date',
            'created_time <= :end_date'
        );

        if ($this->isForTwitter()) {
            return null;
        } else {
            $tableName = 'facebook_stream';
        }

        $sql = '
        SELECT COUNT(1)/SUM(likes) AS av
        FROM ' . $tableName . '
        WHERE ' . implode(' AND ', $clauses);
        $stmt = $this->_db->prepare($sql);
        $stmt->execute(array(':pid'=>$this->id, ':start_date'=>$startDate, ':end_date'=>$endDate));
        return floatval($stmt->fetchColumn());
    }

    /*
     * function to get the ratio of replies to number of posts from others over timeframe
     * */
    public function getRatioRepliesToOthersPosts($startDate, $endDate){
        $clauses = array(
            'presence_id = :pid',
            'created_time >= :start_date',
            'created_time <= :end_date'
        );

        if ($this->isForTwitter()) {
            return 0;
        } else {
            $tableName = 'facebook_stream';
        }

        $sql = '
        SELECT t1.replies/t2.posts as replies_to_others_posts FROM
        (
            SELECT presence_id, COUNT(*) as replies
            FROM ' . $tableName . '
            WHERE ' . implode(' AND ', $clauses) .'
            AND in_response_to IS NOT NULL
        ) as t1,
        (
            SELECT presence_id, COUNT(*) as posts
            FROM ' . $tableName . '
            WHERE ' . implode(' AND ', $clauses) .'
            AND posted_by_owner = 0
        ) as t2';
        $stmt = $this->_db->prepare($sql);
        $stmt->execute(array(':pid'=>$this->id, ':start_date'=>$startDate, ':end_date'=>$endDate));
        return floatval($stmt->fetchColumn());
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

	public function getLinkData($startDate, $endDate) {
		$clauses = array(
			'p.presence_id = :pid',
			'p.created_time >= :start_date',
			'p.created_time <= :end_date'
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
			$clauses[] = 'posted_by_owner = 1';
			$clauses[] = 'in_response_to IS NULL';
		}

		$stmt = $this->_db->prepare("SELECT DATE(p.created_time) AS date, s.status_id, s.url, d.domain, d.is_bc
				FROM domains AS d
				INNER JOIN status_links AS s ON d.domain = s.domain
				INNER JOIN $tableName AS p ON s.status_id = p.id
				WHERE " . implode(' AND ', $clauses));
		$stmt->execute($args);
		return $stmt->fetchAll(PDO::FETCH_OBJ);
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

		$sql = 'SELECT date, COUNT(date) AS value
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
			$counts[$date] = (object)array('date'=>$date, 'value'=>0);
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