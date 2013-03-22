<?php

abstract class Model_TwitterBase extends Model_SocialApiBase {

	const ALL_TWEETS = 'All tweets';

	public static $statuses = array(
		1 => 'Active',
		0 => 'Paused'
	);

	public function getType() {
		$this->type = (get_called_class() == 'Model_TwitterSearch' ? 'search' : 'list');
		return $this->type;
	}

	public function delete() {
		//disable keys for much faster deletes
		$this->_db->exec('LOCK TABLE twitter_tweets WRITE');
		$this->_db->exec('ALTER TABLE twitter_tweets DISABLE KEYS');
		$statement = $this->_db->prepare('DELETE FROM twitter_tweets WHERE parent_id = :id AND parent_type = :type');
		$statement->execute(array(':id'=>$this->id, ':type'=>$this->type));
		$this->_db->exec('ALTER TABLE twitter_tweets ENABLE KEYS');
		$this->_db->exec('UNLOCK TABLE');
		parent::delete();
	}

	public function isActive() {
		return $this->status == 1;
	}

	public function getStatusText() {
		return self::$statuses[$this->status];
	}

//	// gets the tweets for the list
//	public function getTweets($dateRange = null, $topics = array(), $search = null, $ordering = array(), $limit = -1, $offset = -1) {
//		return self::getTweetsForModelIds($this->_db, $this->id, $dateRange, $topics, $search, $ordering, $limit, $offset);
//	}
//
//	// groups all instances of the same topic text, producing an array of objects containing the text, frequency, average importance and average polarity
//	public function getGroupedTopics($dateRange = null, $search = null, $ordering = array(), $limit = -1, $offset = -1) {
//		return self::getGroupedTopicsForModelIds($this->_db, $this->id, $dateRange, $search, $ordering, $limit, $offset);
//	}
//
//	public function getMentions($dateRange, $filter = null, $bucketInterval = 3600) {
//		return self::getMentionsForModelIds($this->_db, $this->id, $dateRange, 'topic', $filter, $bucketInterval);
//	}

	public function countTweetsSince($date) {
		//TODO: this gets slower towards the end of the month. for big searches it takes > 10 min.
		$statement = $this->_db->prepare("SELECT COUNT(*) FROM twitter_tweets
			WHERE parent_type = :type AND parent_id = :id
			AND created_time > :date");

		$statement->execute(array(':id' => $this->id, ':date' => $date, ':type' => $this->type));
		return $statement->fetchColumn();
	}

	protected abstract function getTweetListFromApiResult($apiResult);

	protected function createTweetInsertData($tweet) {
		$parsedTweet = Model_TwitterTweet::parseTweet($tweet);
		return array(
			'tweet_id' => $tweet->id_str,
			'parent_type' => $this->type,
			'parent_id' => $this->id,
			'text_expanded' => $parsedTweet['text_expanded'],
			'twitter_user_id' => $tweet->user->id_str,
			'created_time' => gmdate('Y-m-d H:i:s', strtotime($tweet->created_at)),
			'retweet_count' => $tweet->retweet_count,
//			'json' => json_encode($tweet),
			'html_tweet' => $parsedTweet['html_tweet']
		);
	}

	protected function createUserInsertData($tweet) {
		return array(
			'id' => $tweet->user->id_str,
			'name' => $tweet->user->name,
			'description' => $tweet->user->description,
			'statuses_count' => $tweet->user->statuses_count,
			'profile_image_url' => $tweet->user->profile_image_url,
			'followers_count' => $tweet->user->followers_count,
			'screen_name' => $tweet->user->screen_name,
			'url' => $tweet->user->url,
			'friends_count' => $tweet->user->friends_count,
			'user_last_updated' => gmdate('Y-m-d H:i:s')
		);
	}

	protected abstract function getFetchUrl();
	protected abstract function getFetchArgsArray();

	protected function logApiResult($apiResult) {
		$toLog = $this->getTweetListFromApiResult($apiResult); 
		echo 'found ' . count($toLog) . " tweets\n";
		if ($toLog) {
			foreach ($toLog as $tweet) {
				echo $tweet->id_str, ': ', $tweet->created_at, "\n";
			}
		}
	}

	/**
	 * Fetches and inserts/replaces all tweets in batches (starting with most recent), until
	 * $minTweetId is found, or no tweets are returned
	 * @param $token
	 * @param null $minTweetId
	 * @param null $maxTweetId
	 * @return FetchCount
	 */
	protected function fetchAllTweets($token, $minTweetId = null, $maxTweetId = null) {
//		echo $minTweetId, ', ', $maxTweetId, "\n";
		$counts = new FetchCount(0, 0);
		$url = $this->fetchUrl;
		$argsArray = $this->fetchArgsArray;
		foreach ($argsArray as $args) {
			if ($minTweetId) {
				//TODO check whether system is 64-bit
				$args['since_id'] = function_exists('bcsub') ? bcsub($minTweetId, 1) : $minTweetId - 1;
			}
			if ($maxTweetId) {
				$args['max_id'] = $maxTweetId;
			}
			$repeat = true;
			while ($repeat) {
//	    		print_r($args);
//	    		echo "\n";
				$result = $token->apiRequest($url, $args);

//	    		$this->logApiResult($result);
				$tweetList = $this->getTweetListFromApiResult($result);
				$currentCounts = $this->insertTweets($tweetList);
				$counts->add($currentCounts);

				// using 'max_id' will return a list that includes that tweet (so can't check for empty $tweetList)
				if (!$minTweetId || count($tweetList) <= 1) {
					$repeat = false;
				} else {
					$repeat = true;
					foreach ($tweetList as $tweet) {
						if ($tweet->id_str == $minTweetId) {
//							echo "found expected tweet\n";
							$repeat = false;
							break;
						}
					}
					if ($repeat) {
						$args['max_id'] = $tweetList[count($tweetList)-1]->id_str;
					}
				}
			}
		}
		return $counts;
	}

	// Fetches tweets from the appropriate twitter API.
	public function fetchTweets($token) {
		// get the most recent tweet
		$statement = $this->_db->prepare('SELECT * FROM twitter_tweets
			WHERE parent_type = :type AND parent_id = :id ORDER BY created_time DESC LIMIT 1');
		$statement->execute(array(':type'=>$this->type, ':id'=>$this->id));
		$tweets = $statement->fetchAll(PDO::FETCH_OBJ);

		$minTweetId = ($tweets ? $tweets[0]->tweet_id : null);

		return $this->fetchAllTweets($token, $minTweetId);
	}

	public function refetchTweets($token, $bufferMins = 2) {
		$counts = array();
		$now = gmdate('Y-m-d H:i:s');
		$ages = array(
			'1 hour ago' => '1 HOUR',
			'1 day ago' => '1 DAY',
			'1 week ago' => '1 WEEK'
		);
		foreach ($ages as $label=>$age) {
			$statement = $this->_db->prepare("SELECT * FROM twitter_tweets
				WHERE parent_type = :type AND parent_id = :id
				AND created_time BETWEEN
					:min - INTERVAL $age - INTERVAL $bufferMins MINUTE AND
					:max - INTERVAL $age + INTERVAL $bufferMins MINUTE
				ORDER BY created_time ASC");
			$statement->execute(array(':type'=>$this->type, ':id'=>$this->id, ':min'=>$this->last_fetched, ':max'=>$now));

			$tweets = $statement->fetchAll(PDO::FETCH_OBJ);

			if ($tweets) {
				$minTweetId = $tweets[0]->tweet_id;
				$maxTweetId = $tweets[count($tweets)-1]->tweet_id;
				$ageCounts = $this->fetchAllTweets($token, $minTweetId, $maxTweetId);
				$counts[$label] = $ageCounts->fetched;
			} else {
				$counts[$label] = 0;
			}
		}

		return $counts;
	}

	public function backfillTweets($token, $pages) {
		$path = $this->fetchUrl;
		$argsArray = $this->fetchArgsArray;
		
		// get the oldest tweet
		$statement = $this->_db->prepare('SELECT * FROM twitter_tweets
			WHERE parent_type = :type AND parent_id = :id ORDER BY created_time ASC LIMIT 1');
		$statement->execute(array(':type' => $this->type, ':id' => $this->id));
		$oldestTweet = $statement->fetch(PDO::FETCH_OBJ);

		$counts = new FetchCount(0, 0);

		foreach ($argsArray as $args) {
			if ($oldestTweet) {
				$args['max_id'] = $oldestTweet->tweet_id;
			}

			for ($i=1; $i<=$pages; $i++) {
				$response = $token->apiRequest($path, $args);

				$tweetData = $this->getTweetListFromApiResult($response);

				$currentCounts = $this->insertTweets($tweetData);
				$counts->add($currentCounts);
				if ($currentCounts->fetched == 0) {
					break;
				}

				$args['max_id'] = $tweetData[count($tweetData)-1]->id_str;
			}
		}
		
		if (!$this->last_fetched) {
			$this->last_fetched = gmdate('Y-m-d H:i:s');
			$this->save();
		}
		
		return $counts;
	}
	
	protected function insertTweets($tweetList) {
		$tweetCount = count($tweetList);

		if ($tweetCount > 0) {
			$tweets = array();
			$users = array();
			$shouldAnalyse = $this->should_analyse && $this->campaign->analysis_quota;
			$localTimeZone = new DateTimeZone($this->campaign->timezone);
			$utcTimeZone = new DateTimeZone('UTC');
			foreach ($tweetList as $tweet) {
				$tweetData = $this->createTweetInsertData($tweet);
				$tweetData['is_analysed'] = !$shouldAnalyse;

				$date = DateTime::createFromFormat('Y-m-d H:i:s', $tweetData['created_time'], $utcTimeZone);
				$createdTime = $date->getTimestamp();

				//bucket start time is stored as UTC
				foreach (self::$bucketSizes as $bucket => $size) {
					$bucketStart = $createdTime - ($createdTime + $localTimeZone->getOffset($date)) % $size;
					$tweetData[$bucket] = gmdate('Y-m-d H:i:s', $bucketStart);
				}

				$userData = $this->createUserInsertData($tweet);

				$tweets[$tweetData['tweet_id']] = $tweetData;
				$users[$userData['id']] = $userData;
			}

			self::insertData('twitter_tweets', $tweets);
			self::insertData('twitter_users', $users);

		}

		return new FetchCount($tweetCount, 0);
	}



	
	/********************
	 * static functions
	 ********************/


	public static function fetchUnanalysed($campaign, $limit) {
		$instance = new static();

		$sql = "SELECT tt.*
			FROM twitter_tweets tt
			JOIN {$instance->_tableName} tj ON tt.parent_id = tj.id AND tt.parent_type = :type
			WHERE tt.is_analysed = 0
			AND created_time > DATE_SUB(NOW(), INTERVAL 2 DAY)
			AND tj.campaign_id = :campaign_id
			ORDER BY created_time DESC LIMIT $limit";

		$statement = $instance->_db->prepare($sql);
		$statement->execute(array(':type' => $instance->getType(), ':campaign_id' => $campaign->id));
		return Model_TwitterTweet::objectify($statement);
	}

	public static function getMentionsForModelIds($db, $modelIds, $dateRange, $filterType = null, $filterValue = null, $bucketCol = 'bucket_4_hours') {
		if (!$modelIds) {
			return array();
		}

		list($args, $statusTable, $statusTableWhere) = self::getStatusTableQuery($modelIds);
		$args[':range_start'] = gmdate('Y-m-d H:i:s', strtotime($dateRange[0]));
		$args[':range_end'] = gmdate('Y-m-d H:i:s', strtotime($dateRange[1]));

		if ($filterType == 'topic') {
			$sql = "SELECT COUNT(*) AS mentions, AVG(polarity) AS polarity, $bucketCol AS date
				FROM $statusTable AS status
				INNER JOIN twitter_tweet_topics AS topics ON topics.twitter_tweet_id = status.tweet_id
				WHERE topics.normalised_topic = :topic";
			$args[':topic'] = $filterValue;
		} elseif ($filterType == 'text') {
//			if (strlen($filterValue) <= 3) {
				$matchQuery = "text_expanded LIKE :text";
//				$args[':text'] = '[[:<:]]' . $filterValue . '[[:>:]]';
				$args[':text'] = '%'.$filterValue.'%';
//			} else {
//				$matchQuery = "MATCH(text_expanded) AGAINST (:text)";
//				$args[':text'] = $filterValue;
//			}
			$sql = "SELECT COUNT(*) AS mentions, IFNULL(average_sentiment, 0) AS polarity, $bucketCol as date
				FROM $statusTable AS status
				WHERE $matchQuery";
		} else {
			$sql = "SELECT COUNT(*) as mentions, IFNULL(average_sentiment, 0) AS polarity, $bucketCol AS date
				FROM $statusTable AS status
				WHERE 1";
		}
		$sql .= " AND $statusTableWhere
				AND status.created_time BETWEEN :range_start AND :range_end
				GROUP BY date";

		$statement = $db->prepare($sql);
		$statement->execute($args);

		return $statement->fetchAll(PDO::FETCH_ASSOC);
	}
}