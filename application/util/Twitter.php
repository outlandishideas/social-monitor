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

    /**
     * Gets an array of tweets that mention the user
     * @param $userHandle
     * @param null $minTweetId
     * @param null $maxTweetId
     * @return array
     */
    public static function userMentions($userHandle, $minTweetId = null, $maxTweetId = null) {
        $tweets = array();
        if ($userHandle) {
            $token = self::token();
            $args = array(
                'q'         => '@'.$userHandle,
                'count'           => Zend_Registry::get('config')->twitter->fetch_per_page,
                'exclude_replies' => true,
                'include_rts'     => false,
                'trim_user'       => true
            );
            if ($minTweetId) {
                // since_id is exclusive
                $args['since_id'] = $minTweetId;
            }
            if ($maxTweetId) {
                $args['max_id'] = $maxTweetId;
            }

            do {
                $result = $token->apiRequest('search/tweets', $args);
                $tweets = array_merge($tweets, $result->statuses);

                // if we have a minimum tweet id, and we fetch the exact number we asked for, we likely need to fill in the gap using another request
                $repeat = ($minTweetId && count($result->statuses) == $args['count']);

                if ($repeat) {
                    $lowestId = min(array_map(function($t) { return $t->id_str; }, $result->statuses));
                    //TODO check whether system is 64-bit
                    // max_id is inclusive, so need to subtract 1
                    $args['max_id'] = function_exists('bcsub') ? bcsub($lowestId, 1) : $lowestId - 1;
                }
            } while ($repeat);
        }
        return $tweets;
    }

	/**
	 * Gets an array of tweets for the given user
	 * @param $userId
	 * @param null $minTweetId
	 * @param null $maxTweetId
	 * @return array
	 */
	public static function userTweets($userId, $minTweetId = null, $maxTweetId = null) {
		$tweets = array();
		if ($userId) {
			$token = self::token();
			$args = array(
				'user_id'         => $userId,
				'count'           => Zend_Registry::get('config')->twitter->fetch_per_page,
				'exclude_replies' => false,
				'include_rts'     => true,
				'trim_user'       => true
			);
			if ($minTweetId) {
				// since_id is exclusive
				$args['since_id'] = $minTweetId;
			}
			if ($maxTweetId) {
				$args['max_id'] = $maxTweetId;
			}

			do {
//	    		print_r($args);
//	    		echo "\n";
				$result = $token->apiRequest('statuses/user_timeline', $args);
				$tweets = array_merge($tweets, $result);

//	    		$this->logApiResult($result);

				// if we have a minimum tweet id, and we fetch the exact number we asked for, we likely need to fill in the gap using another request
				$repeat = ($minTweetId && count($result) == $args['count']);

				if ($repeat) {
					$lowestId = min(array_map(function($t) { return $t->id_str; }, $result));
					//TODO check whether system is 64-bit
					// max_id is inclusive, so need to subtract 1
					$args['max_id'] = function_exists('bcsub') ? bcsub($lowestId, 1) : $lowestId - 1;
				}
			} while ($repeat);
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

        $isRetweet = !empty($tweet->retweeted_status->text);

        if ($isRetweet) {
            $htmlTweet = $tweet->retweeted_status->text;
            $entities = $tweet->retweeted_status->entities;
        } else {
            $htmlTweet = $tweet->text;
            $entities = $tweet->entities;
        }
        $expandedText = $htmlTweet;

        $allEntities = array();
        foreach (array('hashtags', 'urls', 'user_mentions'/*, 'media'*/) as $entityType) {
            if (!empty($entities->$entityType)) {
                foreach ($entities->$entityType as $entity) {
                    $entity->entityType = $entityType;
                    $allEntities[] = $entity;
                }
            }
        }
        // reverse sort by start index
        usort($allEntities, function ($a, $b) {
            return (int)$a->indices[0] > (int)$b->indices[0] ? -1 : 1;
        });
        // make all of the substitutions
        foreach ($allEntities as $entity) {
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

        if ($isRetweet) {
            $rt = 'RT <a href="https://twitter.com/' . $tweet->retweeted_status->user->screen_name . '" target="_blank">@' . $tweet->retweeted_status->user->screen_name . '</a>: ';
            $htmlTweet = $rt . $htmlTweet;
            $expandedText = $rt . $expandedText;
        }

		return array(
			'html_tweet' => $htmlTweet,
			'text_expanded' => $expandedText
		);

	}

}
