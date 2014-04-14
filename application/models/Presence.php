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
    const METRIC_SIGN_OFF = 'sign_off';
    const METRIC_BRANDING = 'branding';
    const METRIC_SHARING = 'sharing';
    const METRIC_KLOUT = 'klout_score';
    const METRIC_FB_ENGAGEMENT = 'fb_engagement_score';
    const METRIC_RELEVANCE = 'relevance';

	public static $ALL_METRICS = array(
		self::METRIC_POPULARITY_PERCENT,
		self::METRIC_POPULARITY_TIME,
		self::METRIC_POPULARITY_RATE,
		self::METRIC_POSTS_PER_DAY,
		self::METRIC_RESPONSE_TIME,
	);

	public static $bucketSizes = array(
		'bucket_half_hour' => 1800, // 30*60
		'bucket_4_hours' => 14400, // 4*60*60
		'bucket_12_hours' => 43200, // 12*60*60
		'bucket_day' => 86400 // 24*60*60
	);

	const TYPE_FACEBOOK = 'facebook';
	const TYPE_TWITTER = 'twitter';

	const KLOUT_API_ENDPOINT = 'http://api.klout.com/v2/';

	protected $metrics = array(); // stores the calculated metrics
	protected $kpiData = array();

	public static function fetchAllTwitter() {
		return self::fetchAll('type = :type', array(':type'=>self::TYPE_TWITTER));
	}

	public static function fetchAllFacebook() {
		return self::fetchAll('type = :type', array(':type'=>self::TYPE_FACEBOOK));
	}

	/**
	 * @param string $clause
	 * @param array $args
	 * @return Model_Presence[]
	 */
	public static function fetchAll($clause = null, $args = array()) {
		return parent::fetchAll($clause, $args);
	}


	/**
	 * @param Model_Presence[] $presences
	 * @return Model_Presence[]
	 */
	public static function populateOwners($presences) {
		// fetch all campaigns in one query instead of ~300 individual queries
		$query = BaseController::db()->prepare('SELECT campaign_id, presence_id FROM campaign_presences');
		$query->execute();
		$mapping = array();
		foreach ($query->fetchAll(PDO::FETCH_OBJ) as $row) {
			if (!isset($mapping[$row->campaign_id])) {
				$mapping[$row->campaign_id] = array();
			}
			$mapping[$row->campaign_id][] = $row->presence_id;
		}
		/** @var Model_Presence[] $presences */
		$campaignTypes = array('Model_Country', 'Model_Group', 'Model_Region');
		$campaigns = array();
		foreach ($campaignTypes as $type) {
			$current = array();
			foreach ($type::fetchAll() as $c) {
				if (isset($mapping[$c->id])) {
					foreach ($mapping[$c->id] as $pId) {
						$current[$pId] = $c;
					}
				}
			}
			$campaigns[$type::$countryFilter] = $current;
		}
		foreach ($presences as $p) {
			$p->getOwner($campaigns);
		}
		return $presences;
	}

	public function getPresenceSign($large = true, $classes = array()) {
		$classes[] = 'icon-' . ($this->isForTwitter() ? 'twitter' : 'facebook') . '-sign';
		if ($large) {
			$classes[] = 'icon-large';
		}
		return implode(' ',$classes);
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

	public function statusTable() {
		return $this->isForFacebook() ? 'facebook_stream' : 'twitter_tweets';
	}

	/**
	 * Gets the primary owner of this presence. If $allCampaigns is present, it will be used instead of
	 * querying the database
	 * @param array $allCampaigns
	 * @return Model_Country
	 */
	public function getOwner($allCampaigns = array()) {
		if (!property_exists($this, 'owner')) {
	        $this->owner = null;
			// prioritise country over group and region
			$campaignTypes = array('Model_Country', 'Model_Group', 'Model_Region');
			if ($allCampaigns) {
				foreach($campaignTypes as $campaignType) {
					if (isset($allCampaigns[$campaignType::$countryFilter][$this->id])) {
						$this->owner = $allCampaigns[$campaignType::$countryFilter][$this->id];
						break;
					}
				}
			} else {
				$stmt = $this->_db->prepare('SELECT campaign_id FROM campaign_presences WHERE presence_id = :pid');
				$stmt->execute(array(':pid'=>$this->id));
				$campaignIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
				if ($campaignIds) {
		            foreach($campaignTypes as $campaignType){
		                $owners = $campaignType::fetchAll('id IN (' . implode(',', $campaignIds) . ')');
		                if ($owners) {
		                    $this->owner = $owners[0];
		                    break;
		                }
		            }
				}
			}
		}
		return $this->owner;
	}

	static $kpiCache = array();

	protected function getCachedKpiData($startDateString, $endDateString) {
		for($i=0; $i<5; $i++) {
			// get the data for the given range. If not found, move the window back by one day at a time until data is found
			$key = $startDateString . $endDateString;
			if (!isset(self::$kpiCache[$key])) {
				$kpiCache = array();
				$stmt = $this->_db->prepare('SELECT presence_id, metric, value FROM kpi_cache WHERE start_date = :start AND end_date = :end');
				$stmt->execute(array(':start'=>$startDateString, ':end'=>$endDateString));
				foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
					if (!isset($kpiCache[$row->presence_id])) {
						$kpiCache[$row->presence_id] = array();
					}
					$kpiCache[$row->presence_id][$row->metric] = floatval($row->value);
				}
				self::$kpiCache[$key] = $kpiCache;
			}
			if (isset(self::$kpiCache[$key][$this->id])) {
				return self::$kpiCache[$key][$this->id];
			}
			$startDateString = date('Y-m-d', strtotime($startDateString . ' -1 day'));
			$endDateString = date('Y-m-d', strtotime($endDateString . ' -1 day'));
		}
		return array();
	}

	/**
	 * Calculates the KPIs for this presence, based on the given start and end dates.
	 * If not given, calculates using the last month's worth of data
	 * @param DateTime $startDate
	 * @param DateTime $endDate
	 * @param bool $useCache
	 * @return array
	 */
	public function getKpiData($startDate = null, $endDate = null, $useCache = true) {
		$kpiData = array();

		if (!$startDate || !$endDate) {
			$endDate = new DateTime();
			$startDate = new DateTime();
			$startDate->sub(DateInterval::createFromDateString('1 month'));
		}

		$endDateString = $endDate->format('Y-m-d');
		$startDateString = $startDate->format('Y-m-d');
		$key = $startDateString . $endDateString;

		if (!isset($this->kpiData[$key])) {
			$cachedValues = array();

			if ($useCache) {
				$cachedValues = $this->getCachedKpiData($startDateString, $endDateString);
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

			$this->kpiData[$key] = $kpiData;
		}

		return $this->kpiData[$key];
	}

	public function updateInfo() {
		switch($this->type) {
			case self::TYPE_FACEBOOK:
				try {
					$data = Util_Facebook::pageInfo($this->handle);
				} catch (Exception_FacebookNotFound $e) {
					$this->uid = null;
					throw new Exception_FacebookNotFound('Facebook page not found: ' . $this->handle, $e->getCode(), $e->getFql(), $e->getErrors());
				}
				$this->uid = $data['page_id'];
				$this->image_url = $data['pic_square'];
				$this->name = $data['name'];
				$this->page_url = $data['page_url'];
				$this->popularity = $data['fan_count'];
                $this->facebook_engagement = $this->calculateFacebookEngagement();
				break;
			case self::TYPE_TWITTER:
				try {
					$data = Util_Twitter::userInfo($this->handle);
				} catch (Exception_TwitterNotFound $e) {
					$this->uid = null;
					throw new Exception_TwitterNotFound('Twitter user not found: ' . $this->handle, $e->getCode(), $e->getPath(), $e->getErrors());
				}
				$this->uid = $data->id_str;
				$this->image_url = $data->profile_image_url;
				$this->name = $data->name;
				$this->page_url = 'http://www.twitter.com/' . $data->screen_name;
				$this->popularity = $data->followers_count;

				// update the klout score (not currently possible for facebook pages)
				try {
					$apiKey = Zend_Registry::get('config')->klout->api_key;
					if (!$this->klout_id) {
						$json = Util_Http::fetchJson(self::KLOUT_API_ENDPOINT . 'identity.json/tw/' . $this->uid . '?key=' . $apiKey);
						$this->klout_id = $json->id;
					}
					if ($this->klout_id) {
						$json = Util_Http::fetchJson(self::KLOUT_API_ENDPOINT . 'user.json/' . $this->klout_id . '?key=' . $apiKey);
						$this->klout_score = $json->score->score;
					}
				} catch (Exception $ex) { /* ignore */ }

				break;
		}
	}

	public function getTypeLabel() {
		return ucfirst($this->type);
	}

	/**
	 * Re-fetches the statuses from the time window between the last fetch and now (minus $age) and
	 * updates their changeable information (retweets/shares/likes/comments)
	 * @param string $age
	 * @throws Exception
	 */
	public function refetchStatusInfo($age = '2 days') {
		if (!$this->uid) {
			throw new Exception('Presence not initialised/found');
		}

		$buffer = '2 minutes';
		$max = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' - ' . $age . ' - ' . $buffer));
		$min = date('Y-m-d H:i:s', strtotime($this->last_fetched . ' - ' . $age . ' - ' . $buffer));
		$tableName = $this->statusTable();
		if ($this->isForTwitter()) {
			$statement = $this->_db->prepare("SELECT tweet_id FROM $tableName
				WHERE presence_id = :id
				AND created_time BETWEEN :min AND :max
				ORDER BY created_time ASC");
			$statement->execute(array(':id'=>$this->id, ':min'=>$min, ':max'=>$max));

			$tweetIds = $statement->fetchAll(PDO::FETCH_COLUMN);
			if ($tweetIds) {
				$updateStatement = $this->_db->prepare("UPDATE $tableName SET retweet_count = :retweets WHERE tweet_id = :tweet_id");
				$minTweetId = $tweetIds[0];
				// min_id is exclusive, so need to subtract one
				$minTweetId = function_exists('bcsub') ? bcsub($minTweetId, 1) : $minTweetId - 1;
				$maxTweetId = $tweetIds[count($tweetIds)-1];
				$tweets = Util_Twitter::userTweets($this->uid, $minTweetId, $maxTweetId);
				foreach ($tweets as $tweet) {
					if ($tweet->retweet_count > 0) {
						$updateStatement->execute(array(':retweets'=>$tweet->retweet_count, ':tweet_id'=>$tweet->id_str));
					}
				}
			}
		} else {
			$statement = $this->_db->prepare("SELECT created_time FROM $tableName
				WHERE presence_id = :id
				AND posted_by_owner = 1
				AND created_time BETWEEN :min AND :max
				ORDER BY created_time ASC");
			$statement->execute(array(':id'=>$this->id, ':min'=>$min, ':max'=>$max));

			$postTimes = $statement->fetchAll(PDO::FETCH_COLUMN);
			if ($postTimes) {
				$updateStatement = $this->_db->prepare("UPDATE $tableName SET comments = :comments, likes = :likes, share_count = :share_count WHERE post_id = :post_id");
				$minPostTime = strtotime($postTimes[0] . ' - 1 second');
				$maxPostTime = strtotime($postTimes[count($postTimes)-1] . ' + 1 second');
				$posts = Util_Facebook::pagePosts($this->uid, $minPostTime, $maxPostTime);
				foreach ($posts as $post) {
					$comments = isset($post->comments['count']) ? intval($post->comments['count']) : 0;
					$likes = isset($post->likes['count']) ? intval($post->likes['count']) : 0;
					$shareCount = $post->share_count;
					if ($comments > 0 || $likes > 0 || $shareCount > 0) {
						$updateStatement->execute(array(':comments'=>$comments, ':likes'=>$likes, ':share_count'=>$shareCount, ':post_id'=>$post->post_id));
					}
				}
			}
		}
	}

	/**
	 * Fetches the posts/tweets for the presence, and inserts them into the database
	 * @return Util_FetchCount
	 * @throws Exception
	 */
	public function updateStatuses() {
		if (!$this->uid) {
			throw new Exception('Presence not initialised/found');
		}

		$statuses = array();
		$responses = array();
		$links = array();
		$tableName = $this->statusTable();
		$fetchCount = new Util_FetchCount(0, 0);
		if ($this->isForFacebook()) {
			$fetchCount->type = 'post';
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
					'comments' => isset($post->comments['count']) ? intval($post->comments['count']) : 0,
					'likes' => isset($post->likes['count']) ? intval($post->likes['count']) : 0,
					'share_count' => $post->share_count,
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
		} else {
			$fetchCount->type = 'tweet';
			$stmt = $this->_db->prepare("SELECT tweet_id FROM $tableName WHERE presence_id = :id ORDER BY created_time DESC LIMIT 1");
			$stmt->execute(array(':id'=>$this->id));
			$lastTweetId = $stmt->fetchColumn();
			$tweets = Util_Twitter::userTweets($this->uid, $lastTweetId);
            $mentions = Util_Twitter::userMentions($this->handle, $lastTweetId);
            $statusesTweets = $this->extractTweets($tweets);
            $statusesMentions = $this->extractTweets($mentions, true);
            $statuses = array_merge($statusesTweets, $statusesMentions);
		}

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

    private function extractTweets($tweets = array(), $mentions = false){
        $statuses = array();
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
                'responsible_presence' => $mentions ? $this->id : null,
                'needs_response' => $mentions && !$this->isRetweet($tweet) ? 1 : 0,
                'in_reply_to_user_uid' => $tweet->in_reply_to_user_id_str,
                'in_reply_to_status_uid' => $tweet->in_reply_to_status_id_str
            );
        }
        return $statuses;
    }

    private function isRetweet($tweet){
        if(isset($tweet->retweeted_status) && $tweet->retweeted_status->user->id == $this->uid) {
            return true;
        } else {
            return false;
        }
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

    /**
     * Get the relevance KPI for a presence
     * Finds all the statuses in a given time period ($startDate -> $endDate)
     * todo: neaten this up as some data isn't need (eg. total actions, and total links)
     * @param $startDate
     * @param $endDate
     * @return float
     */
    public function getRelevance($startDate, $endDate)
    {
        //get array of row objects of statuses, their links, and their bc links
        //data is grouped by day
        $data = $this->getRelevanceData($startDate, $endDate);

        //set up variables to capture sum of actions, links, and bc links
        $total = 0;
        $total_links = 0;
        $total_bc_links = 0;

        //returned row won't cover all days, as some days no posts are made.
        //To get the correct number of days to divide the result by get diff between the two days and +1 (as we are inclusive)
        $diff = date_diff(new DateTime($startDate), new DateTime($endDate), true);
        $days = $diff->days + 1;

        //sum the actions, links and bc links across all days.
        foreach($data as $day)
        {
            $total += $day->total;
            $total_links += $day->total_links;
            $total_bc_links += $day->total_bc_links;
        }

        //calculate the target
        //target is a percentage of the total actions per day
        //however target must reach a minimum, which is the percentage of the target actions per day
        //EXAMPLE:
        //Target Actions per Day = 5, Target Relevant Actions per Day = 60% (min 3)
        //1 relevant post out of 1 post on 1 day will not satisfy this metric
        //3 relevant posts out of 10 posts on 1 day will not satisfy this metric (if metric is 60%)

        $targetPercent = BaseController::getOption($this->isForFacebook() ? 'facebook_relevance_percentage' : 'twitter_relevance_percentage' );
        $target = max( $total / $days, BaseController::getOption('updates_per_day') ) / 100 * $targetPercent;

        //return target and actual
        return array(
            'target' => $target,
            'actual' => $total_bc_links / $days );
    }

    /**
     * gets the number of actions per day, links per day and bc links per day
     * @param $startDate
     * @param $endDate
     * @return array
     */
    public function getRelevanceData($startDate, $endDate)
    {

        $args = array(
            ':pid' => $this->id,
            ':start_time' => $startDate,
            ':end_time' => $endDate
        );

        $sql ="
            SELECT DATE(fs.created_time) as created_time, COUNT(fs.id) as total, COUNT(sl.domain) as total_links, IFNULL(SUM(IF(d.is_bc=1,1,NULL)), 0) as total_bc_links
            FROM facebook_stream as fs
            LEFT JOIN status_links as sl ON fs.id = sl.status_id
            LEFT JOIN domains as d ON sl.domain = d.domain
            WHERE presence_id = :pid
                AND posted_by_owner = 1
                AND DATE(fs.created_time) >= :start_time
                AND DATE(fs.created_time) <= :end_time
            GROUP BY DATE(fs.created_time)";

        $stmt = $this->_db->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll(PDO::FETCH_OBJ);

    }

    /**
     * returns the facebook engagment score based on the following calculation
     * [(Likes + Comments + Shares) 7 day sum ] / popularity.
     * @return float
     */
    public function calculateFacebookEngagement()
    {
        $week = new DateInterval('P6D');
        $endDate = new DateTime();
        $startDate = clone $endDate;
        $startDate = $startDate->sub($week);

        $total = 0;

        $s = $this->getFacebookCommentsSharesLikes($startDate->format('Y-m-d'), $endDate->format('Y-m-d'));

        if(empty($s)) return 0;

        foreach($s as $i => $status)
        {
            $total += $status->comments + $status->likes + $status->share_count;
        }

        if(!$total) return 0;

        $last = end($s);

        return ($total / $last->popularity) * 1000;
    }

    /**
     * returns the facebook engagment scores from the presence history table
     * @param $startDate
     * @param $endDate
     * @return float
     */
    public function getFacebookEngagementScore($startDate, $endDate)
    {
        $data = $this->getHistoryData('facebook_engagement_score', $startDate, $endDate);

        if(empty($data)) return 0;

        $total = 0;

        foreach($data as $d)
        {
            $total += $d;
        }

        return $total/count($data);
    }

    public function getFacebookCommentsSharesLikes($startDate, $endDate)
    {
        $args = array(
            ':pid' => $this->id,
            ':start_time' => $startDate,
            ':end_time' => $endDate
        );

        $sql = "
            SELECT ph.created_time as time, SUM(fs.comments) as comments, SUM(fs.likes) as likes, SUM(fs.share_count) as share_count, ph.popularity
            FROM (
                SELECT presence_id, DATE(datetime) as created_time, MAX(value) as popularity
                FROM presence_history
                WHERE type = 'popularity'
                AND presence_id = :pid
                AND DATE(datetime) >= :start_time
                AND DATE(datetime) <= :end_time
                GROUP BY DATE(datetime) ) as ph
            LEFT JOIN facebook_stream as fs
            ON DATE(fs.created_time) = ph.created_time
            AND fs.presence_id = ph.presence_id
            WHERE ph.created_time >= :start_time
            AND ph.created_time <= :end_time
            GROUP BY ph.created_time";

        $stmt = $this->_db->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

	public function getMetrics($badgeType = null){
		switch ($badgeType) {
			case Model_Badge::BADGE_TYPE_ENGAGEMENT:
			case Model_Badge::BADGE_TYPE_QUALITY:
			case Model_Badge::BADGE_TYPE_REACH:
				$metrics = Model_Badge::metrics($badgeType);
				break;
			case Model_Badge::BADGE_TYPE_TOTAL:
			default:
				$metrics = array_merge(
					Model_Badge::metrics(Model_Badge::BADGE_TYPE_ENGAGEMENT),
					Model_Badge::metrics(Model_Badge::BADGE_TYPE_QUALITY),
					Model_Badge::metrics(Model_Badge::BADGE_TYPE_REACH)
				);
		}
		$metricsArray = array();
		foreach ($metrics as $m=>$weight) {
			$metricsArray[$m] = $this->calculateMetric(null, $m, 'month');
		}
		return $metricsArray;
	}

	public function getMetricsScore($date, $metrics, $dateRange = "month") {

		$score = 0;
		$count = 0;
		foreach ($metrics as $m=>$weight) {
			$metricData = $this->calculateMetric($date, $m, $dateRange);
			$score += $weight*$metricData->score;
			$count += $weight;
		}

		return $score / $count;
	}

	/**
	 * Calculates the score for a given metric on a given day, over a given range
	 * @param $date string
	 * @param $metric string
	 * @param $dateRange string
	 * @return object
	 */
	public function calculateMetric($date = null, $metric, $dateRange)
	{
		$endDate = $date ?: date('Y-m-d');
		$cacheKey = $metric . '-' . $endDate . '-' . $dateRange;
		if (isset($this->metrics[$cacheKey])) {
			return $this->metrics[$cacheKey];
		}
		$startDate = date('Y-m-d', strtotime($endDate . ' -1 ' . $dateRange));

		$invert = false;

		switch($metric){
            case Model_Presence::METRIC_SIGN_OFF:
                $title = 'Sign Off';
                $target = '1';
                $actual = $this->sign_off;
                break;

            case Model_Presence::METRIC_BRANDING:
                $title = 'Popularity';
                $target = '1';
                $actual = $this->branding;
                break;

			case Model_Presence::METRIC_POPULARITY_PERCENT:
				$title = 'Popularity';
				$target = $this->getTargetAudience();
				$actual = $this->popularity;
				break;

            case Model_Presence::METRIC_RELEVANCE:
                $title = 'Relevance';
                $data = $this->getRelevance($startDate, $endDate);
                $target = $data['target'];
                $actual = $data['actual']; ;
                break;

			case Model_Presence::METRIC_POPULARITY_TIME:
				$title = 'Popularity Trend';
				$target = BaseController::getOption('achieve_audience_good');
				$date = $this->getTargetAudienceDate($startDate, $endDate);
				$now = new DateTime();
				$estimate = new DateTime($date);
				$interval = $estimate->diff($now);
				$actual = $interval->y*12 + $interval->m;
				$invert = true;
				break;

            case Model_Presence::METRIC_KLOUT:
                $title = 'Klout Score';
                $target = BaseController::getOption('klout_target');
                $actual = round($this->klout_score);
                $score = ($actual < $target) ? 0 : 100 ;
                break;

            case Model_Presence::METRIC_FB_ENGAGEMENT:
                $title = 'Facebook Engagement Score';
                $target = BaseController::getOption('fb_engagement_target');
                $actual = round($this->getFacebookEngagementScore($startDate, $endDate));
                $score = ($actual < $target) ? 0 : 100 ;
                break;

			case Model_Presence::METRIC_POSTS_PER_DAY:
				$title = 'Average Actions Per Day';
				$target = BaseController::getOption('updates_per_day');
				$actual = $this->getAveragePostsPerDay($startDate, $endDate);
				break;

			case(Model_Presence::METRIC_LINKS_PER_DAY):
				$title = 'Average Links Per Day';
				$target = BaseController::getOption('links_per_day');
				$actual = $this->getAverageLinksPerDay($startDate, $endDate);
				break;

			case Model_Presence::METRIC_LIKES_PER_POST:
				$title = 'Applause';
				$target = BaseController::getOption('likes_per_post_best');
				$actual = $this->getAverageLikesPerPost($startDate, $endDate);
				break;

			case Model_Presence::METRIC_RESPONSE_TIME:
				$title = 'Responsiveness';
				$target = BaseController::getOption('updates_per_day');
				$actual = $this->getAverageResponseTime($startDate, $endDate);
                if($actual === 0) $score = 0;
				$invert = true;
				break;

			case Model_Presence::METRIC_RATIO_REPLIES_TO_OTHERS_POSTS:
				$title = 'Conversation';
				$target = BaseController::getOption('replies_to_number_posts_best');
				$actual = $this->getRatioRepliesToOthersPosts($startDate, $endDate);
				break;

			case Model_Presence::METRIC_SHARING:
				$title = 'Shares/Retweets';
				if ($this->isForFacebook()) {
					$target = BaseController::getOption('fb_share');
				} else {
					$target = BaseController::getOption('tw_retweet');
				}
				$target = $target * $this->getTargetAudience() / 100;
				$actual = $this->getAverageSharesPerStatus($startDate, $endDate);
				break;

			default:
				$title = 'Default';
				$target = 0;
				$actual = 0;
				$invert = true;
				break;
		}

        //if score has not already been set, generate it
        if(!isset($score)){

            if ($invert) {
                if(!$target){
                    $score = 0;
                } else if($actual > $target){
                    $score = ( $target / $actual ) * 100;
                } else {
                    $score = 100;
                }
            } else {
                if($target === 0){
                    $score = 100;
                } else if(!$target){
                    $score = 0;
                }  else if($actual > $target){
                    $score = 100;
                } else {
                    $score = ( $actual / $target ) * 100;
                }
            }

        }

		$metric = (object)array(
			'score' => $score,
			'actual' => $actual,
			'target' => $target,
			'type' => $metric,
			'title' => $title
		);
		$this->metrics[$cacheKey] = $metric;
		return $metric;
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

		$tableName = $this->statusTable();
		if ($this->isForFacebook()) {
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

		$tableName = $this->statusTable();
		if ($this->isForFacebook()) {
			$clauses[] = 'posted_by_owner = 1';
			$clauses[] = 'in_response_to IS NULL';
			$linkType = 'facebook';
		} else {
			$linkType = 'twitter';
		}

		$where = implode(' AND ', $clauses);
		$sql = "
		SELECT SUM(link_count) AS links, COUNT(id) AS posts, SUM(link_count)/COUNT(id) as av FROM
		(
			SELECT status.*, COUNT(link.id) AS link_count
			FROM $tableName AS status
			LEFT JOIN status_links AS link ON link.type = '$linkType' AND status.id = link.status_id
			WHERE $where
			GROUP BY status.id
		) as data";

//		$sql = '
//		SELECT SUM(links.count) AS links, COUNT(statuses.id) AS posts, SUM(links.count)/COUNT(statuses.id) as av
//		FROM ' . $tableName . ' AS statuses
//		LEFT JOIN (
//			SELECT status_id, COUNT(url) as count FROM `status_links` GROUP BY status_id
//		) AS links ON statuses.id = links.status_id
//		WHERE ' . implode(' AND ', $clauses);
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
		}

		$sql = '
		SELECT COUNT(1)/SUM(likes) AS av
		FROM ' . $this->statusTable() . '
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
		}

		$tableName = $this->statusTable();
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

		$tableName = $this->statusTable();
		if ($this->isForFacebook()) {
			$clauses[] = 'posted_by_owner = 1';
		}

		$sql = 'SELECT COUNT(1)/DATEDIFF(:end_date, :start_date) AS av FROM ' . $tableName . ' WHERE ' . implode(' AND ', $clauses);
		$stmt = $this->_db->prepare($sql);
		$stmt->execute(array(':pid'=>$this->id, ':start_date'=>$startDate, ':end_date'=>$endDate));
		return floatval($stmt->fetchColumn());
	}

	public function getAverageResponseTime($startDate, $endDate) {
		$data = $this->getResponseData($startDate, $endDate);
		$maxTime = floatval(BaseController::getOption('response_time_bad'));
		$totalTime = 0;
		if ($data) {
			foreach ($data as $row) {
				$diff = min($maxTime, $row->diff);
				$totalTime += $diff;
			}
			return $totalTime/count($data);
		} else {
			return 0;
		}
	}

	public function getAverageSharesPerStatus($startDate, $endDate) {
		$tableName = $this->statusTable();
		if ($this->isForFacebook()) {
			$column = 'share_count';
		} else {
			$column = 'retweet_count';
		}
		$clauses = array(
			'presence_id = :pid',
			'created_time >= :start_date',
			'created_time <= :end_date',
			$column . ' > 0'
		);
		$sql = "SELECT AVG($column) FROM $tableName WHERE " . implode(' AND ', $clauses);
		$stmt = $this->_db->prepare($sql);
		$stmt->execute(array(':pid'=>$this->id, ':start_date'=>$startDate, ':end_date'=>$endDate));
		$average = floatval($stmt->fetchColumn());
		return $average;
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

		$tableName = $this->statusTable();
		if ($this->isForFacebook()) {
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

		$tableName = $this->statusTable();
		if ($this->isForFacebook()) {
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
        $tableName = $this->statusTable();
        if ($this->isForFacebook()) {
            $clauses = array(
                'r.presence_id = :pid',
                't.created_time >= :start_date',
                't.created_time <= :end_date'
            );
            $args = array(':pid'=>$this->id,':start_date' => $startDate, ':end_date' => $endDate);
            $stmt = $this->_db->prepare("
              SELECT t.post_id as id, t.created_time as created, TIME_TO_SEC( TIMEDIFF( r.created_time, t.created_time ))/3600 AS time
              FROM $tableName AS t
                INNER JOIN $tableName AS r ON t.post_id = r.in_response_to
                WHERE " . implode(' AND ', $clauses) ."");
            $stmt->execute($args);
            foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $r) {
                $key = $r->id;
                if(!array_key_exists($key, $responseData)) $responseData[$key] = (object)array('diff' => null, 'created' => null);
                if (empty($responseData[$key]->diff) || $r->time < $responseData[$key]->diff) {
                    $responseData[$key]->diff = $r->time;
                    $responseData[$key]->created = $r->created;
                }
            }
		} else {
            $clauses = array(
                't.responsible_presence = :pid',
                't.needs_response = 1',
                't.created_time >= :start_date',
                't.created_time <= :end_date'
            );
            $args = array(':pid'=>$this->id,':start_date' => $startDate, ':end_date' => $endDate);
            $stmt = $this->_db->prepare("
              SELECT t.tweet_id as id, t.created_time as created, TIME_TO_SEC( TIMEDIFF( r.created_time, t.created_time ))/3600 AS time
              FROM $tableName AS t
                INNER JOIN $tableName AS r ON t.tweet_id = r.in_reply_to_status_uid
                WHERE " . implode(' AND ', $clauses) ."");
            $stmt->execute($args);
            foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $r) {
                $key = $r->id;
                if(!array_key_exists($key, $responseData)) $responseData[$key] = (object)array('diff' => null, 'created' => null);
                if (empty($responseData[$key]->diff) || $r->time < $responseData[$key]->diff) {
                    $responseData[$key]->diff = $r->time;
                    $responseData[$key]->created = $r->created;
                }
            }
        }
		return $responseData;
	}

	public function getTargetAudience() {
		$target = 0;
		$owner = $this->getOwner();
//		if ($owner) {
//			$target = $owner->getTargetAudience();
//            $target /= $owner->getPresenceCount();
//            $target *= BaseController::getOption($this->isForFacebook() ? 'fb_min' : 'tw_min');
//            $target /= 100;
//			$target = round($target);
//		}
        if ($owner) {
            $target = $owner->getTargetAudience();
            $target *= BaseController::getOption($this->isForFacebook() ? 'fb_min' : 'tw_min');
            $target /= 100;
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
		$tables = array(
			'campaign_presences',
			'presence_history',
			$this->statusTable()
		);
		foreach ($tables as $table) {
			$this->_db->prepare("DELETE FROM $table WHERE presence_id = :pid")->execute(array(':pid'=>$this->id));
		}
		parent::delete();
	}

	/**
	 * Gets the badges for this presence
	 * @return array
	 */
	public function badges(){
		$data = Model_Badge::badgesData(true);
		$badges = array();
		$presenceCount = static::countAll();

		if (isset($data[$this->id])) {
			$badgeData = $data[$this->id];
			foreach(Model_Badge::$ALL_BADGE_TYPES as $type){
				$badges[$type] = (object)array(
					'type'=>$type,
					'score'=>floatval($badgeData->{$type}),
					'rank'=>intval($badgeData->{$type.'_rank'}),
					'rankTotal'=>$presenceCount,
					'metrics'=>$this->getMetrics($type)
				);
			}
		} else {
			foreach(Model_Badge::$ALL_BADGE_TYPES as $type){
				$badges[$type] = (object)array(
					'type'=>$type,
					'score'=>0,
					'rank'=>$presenceCount,
					'rankTotal'=>$presenceCount,
					'metrics'=>$this->getMetrics($type)
				);
			}
		}

		return $badges;
	}

}