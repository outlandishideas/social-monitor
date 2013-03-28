<?php

class Util_Twitter {
	private static $_token;

	/**
	 * @static
	 * @return Model_TwitterToken instance of Twitter Token object
	 */
	public static function token() {

		if (!isset(self::$_token)) {
			self::$_token = new Model_TwitterToken();
		}

		return self::$_token;
	}

	public static function userInfo($screenName) {
		return self::token()->apiRequest('users/show', array('screen_name'=>$screenName));
	}

	public static function userTweets($userId, $minTweetId = null) {
		$token = self::token();
		$args = array(
			'user_id'         => $userId,
			'count'           => Zend_Registry::get('config')->twitter->fetch_per_page,
			'exclude_replies' => false,
			'include_rts'     => true,
			'trim_user'       => true
		);
		if ($minTweetId) {
			//TODO check whether system is 64-bit
			$args['since_id'] = function_exists('bcsub') ? bcsub($minTweetId, 1) : $minTweetId - 1;
		}
		$repeat = true;
		$tweets = array();
		while ($repeat) {
//	    		print_r($args);
//	    		echo "\n";
			$result = $token->apiRequest('statuses/user_timeline', $args);

			$tweets += $result;
//	    		$this->logApiResult($result);

			// using 'min_id' or 'max_id' will return a list that includes that tweet (so can't check for empty $result)
			if (!$minTweetId || count($result) <= 1) {
				$repeat = false;
			} else {
				// look for minTweetId. If not found, need to repeat using a max_id to look back further
				$repeat = true;
				foreach ($result as $tweet) {
					if ($tweet->id_str == $minTweetId) {
//							echo "found expected tweet\n";
						$repeat = false;
						break;
					}
				}
				if ($repeat) {
					$args['max_id'] = array_pop($result)->id_str;
				}
			}

		}
		return $tweets;
	}

	// Fetches tweets from the appropriate twitter API.
//	public function fetchTweets($token) {
//		// get the most recent tweet
//		$statement = $this->_db->prepare('SELECT * FROM twitter_tweets
//			WHERE parent_type = :type AND parent_id = :id ORDER BY created_time DESC LIMIT 1');
//		$statement->execute(array(':type'=>$this->type, ':id'=>$this->id));
//		$tweets = $statement->fetchAll(PDO::FETCH_OBJ);
//
//		$minTweetId = ($tweets ? $tweets[0]->tweet_id : null);
//
//		return $this->fetchUserTweets($token, $minTweetId);
//	}
//
//	public function refetchTweets($token, $bufferMins = 2) {
//		$counts = array();
//		$now = gmdate('Y-m-d H:i:s');
//		$ages = array(
//			'1 hour ago' => '1 HOUR',
//			'1 day ago' => '1 DAY',
//			'1 week ago' => '1 WEEK'
//		);
//		foreach ($ages as $label=>$age) {
//			$statement = $this->_db->prepare("SELECT * FROM twitter_tweets
//				WHERE parent_type = :type AND parent_id = :id
//				AND created_time BETWEEN
//					:min - INTERVAL $age - INTERVAL $bufferMins MINUTE AND
//					:max - INTERVAL $age + INTERVAL $bufferMins MINUTE
//				ORDER BY created_time ASC");
//			$statement->execute(array(':type'=>$this->type, ':id'=>$this->id, ':min'=>$this->last_fetched, ':max'=>$now));
//
//			$tweets = $statement->fetchAll(PDO::FETCH_OBJ);
//
//			if ($tweets) {
//				$minTweetId = $tweets[0]->tweet_id;
//				$maxTweetId = $tweets[count($tweets)-1]->tweet_id;
//				$ageCounts = $this->fetchUserTweets($token, $minTweetId, $maxTweetId);
//				$counts[$label] = $ageCounts->fetched;
//			} else {
//				$counts[$label] = 0;
//			}
//		}
//
//		return $counts;
//	}

	/**
	 * does a substring replacement using the indices for choosing the substring range
	 * @param $originalText string
	 * @param $replacement string
	 * @param $indices array
	 * @return string
	 */
	protected static function replaceTweetSubstring($originalText, $replacement, $indices) {
		return
			mb_substr($originalText, 0, $indices[0], 'utf-8') .
			$replacement .
			mb_substr($originalText, $indices[1], 9999, 'utf-8');
	}

	/**
	 * Substitutes all of the entities into the tweet, returning an array containing:
	 * - text_expanded: the tweet, with all urls expanded to their original url
	 * - html_tweet: the tweet, with all entities converted to links
	 * @param $tweet
	 * @return array
	 */
	public static function parseTweet($tweet) {

		$htmlTweet = $tweet->text;
		$expandedText = $tweet->text;

		if (array_key_exists('entities', $tweet)) {
			$entities = array();
			foreach (array('hashtags', 'urls', 'user_mentions'/*, 'media'*/) as $entityType) {
				if (!empty($tweet->entities->$entityType)) {
					foreach ($tweet->entities->$entityType as $entity) {
						$entity->entityType = $entityType;
						$entities[] = $entity;
					}
				}
			}
			// reverse sort by start index
			usort($entities, function ($a, $b) {
				return (int)$a->indices[0] > (int)$b->indices[0] ? -1 : 1;
			});
			// make all of the substitutions
			foreach ($entities as $entity) {
				switch ($entity->entityType) {
					case 'hashtags':
						$replace = '<a href="https://twitter.com/search/%23'.$entity->text.'" target="_blank">#'.$entity->text.'</a>';
						$htmlTweet = self::replaceTweetSubstring($htmlTweet, $replace, $entity->indices);
						break;
					case 'urls':
						// linkify urls, but use the full url (not t.co)
						//display_url is sometimes missing and expanded_url is sometimes null
						if (empty($entity->display_url)) $entity->display_url = $entity->url;
						if (empty($entity->expanded_url)) $entity->expanded_url = $entity->url;
						$replace = '<a href="' . $entity->expanded_url . '" target="_blank">' . $entity->display_url . '</a>';
						$htmlTweet = self::replaceTweetSubstring($htmlTweet, $replace, $entity->indices);
						$expandedText = self::replaceTweetSubstring($expandedText, $replace, $entity->indices);
						break;
					case 'user_mentions':
						$replace = '<a href="https://twitter.com/' . $entity->screen_name . '" target="_blank">@' . $entity->screen_name . '</a>';
						$htmlTweet = self::replaceTweetSubstring($htmlTweet, $replace, $entity->indices);
						break;
//					case 'media':
//						$replacement = '<a href="' . $entity->url . '" class="media">' . $entity->display_url . '</a>';
//						break;
				}
			}
		}

		return array(
			'html_tweet' => $htmlTweet,
			'text_expanded' => $expandedText
		);

	}

}
