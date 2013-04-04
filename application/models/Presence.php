<?php

include APP_ROOT_PATH."/lib/facebook/facebook.php";

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
	 * @param int $days how far back to look
	 * @return array a series of (date, value) data points
	 */
	private function getHistoryData($type, $days = 7) {
		$date = gmdate('Y-m-d', strtotime('-' . $days . ' days'));
		$stmt = $this->_db->prepare('SELECT datetime, value
			FROM presence_history
			WHERE presence_id = :id
			AND type = :type
			AND datetime >= :date
			ORDER BY datetime ASC');
		$stmt->execute(array(':id'=>$this->id, ':type'=>$type, ':date'=>$date));
		return $stmt->fetchAll(PDO::FETCH_OBJ);
	}

    public function getPopularityData($days){
        return $this->getHistoryData('popularity', $days);
    }

    public function getRecentPopularityData(){
        $stmt = $this->_db->prepare('SELECT popularity, handle
			FROM presences
			WHERE presence_id = :id');
        $stmt->execute(array(':id'=>$this->id));
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

	/**
	 * Gets the date at which the target audience size will be reached, based on the current trend. The date may be in the past
	 * If any of these conditions are met, this will return null:
	 * - no target audience size
	 * - fewer than 2 data points
	 * - popularity hasn't varied at all
	 * - the calculated date would be too far in the future (32-bit date problem)
	 * @return null|string
	 */
	public function getTargetAudienceDate() {
		$date = null;
		$country = $this->getCountry();
		if ($country && $country->audience > 0) {
			$data = $this->getHistoryData('popularity');
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
				$target = 0.5*$country->audience;
				if ($a != 0) {
					$timestamp = ($target - $b)/$a;
					if ($timestamp < PHP_INT_MAX) {
						$date = date('Y-m-d', $timestamp);
					}
				}
			}
		}
		return $date;
	}

    public function getTargetAudienceDatePercent(){
        $days = 365; //base line for percentage is one year

        $trendDate = new DateTime($this->getTargetAudienceDate());
        $targetDate = new DateTime("now");
        $targetDate->add(new DateInterval('P1Y'));
        $diff = $trendDate->diff($targetDate);

        return ($days/100)*($diff->days);

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

    // gets the appropriate status (tweet/post) table and corresponding where clause(s)
    protected static function getStatusTableQuery($modelIds) {

        if (!is_array($modelIds)) {
            $modelIds = array($modelIds);
        }

        $statusType = self::fetchById($modelIds[0])->typeLabel;

        if ($statusType == 'twitter') {
            $classname = get_called_class();
            $table = 'twitter_tweets';
        } else {
            $parentType = 'page';
            $table = 'facebook_stream';
        }

        $args = array();
        $modelIds = implode(',', $modelIds);
        $where = "presence_id IN ($modelIds)";

        return array($args, $table, $where);
    }

    public static function getMentionsForModelIds($db, $modelIds, $dateRange, $filterType = null, $filterValue = null, $bucketCol = 'bucket_4_hours') {
        if (!$modelIds) {
            return array();
        }

        $args = array();
        $args[':range_start'] = gmdate('Y-m-d H:i:s', strtotime($dateRange[0]));
        $args[':range_end'] = gmdate('Y-m-d H:i:s', strtotime($dateRange[1]));

        $sql = "SELECT value AS popularity, datetime AS date
            FROM presence_history AS history
            WHERE presence_id IN ($modelIds)
            AND date BETWEEN :range_start AND :range_end
            GROUP BY date";

        $statement = $db->prepare($sql);
        $statement->execute($args);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    // gets tweets or posts, depending on the class this is called on
    public static function getStatusesForModelIds($db, $linePropArray, $dateRange = null, $search = null, $ordering = array(), $limit = -1, $offset = -1) {
        if (!$linePropArray) {
            return array();
        }

        $options = array();
        $optionArgs = array();

        $statusType = self::getStatusType();
        if ($statusType == 'tweet') {
            $textMatchColumn = 'text_expanded';
        } else {
            $textMatchColumn = 'message';
        }

        $options['order'] = $ordering ? $ordering : array('created_time' => 'DESC');
        $options['where'] = array();

        if ($dateRange) {
            $options['where'][] = "created_time >= :min_date";
            $options['where'][] = "created_time < :max_date";
            $optionArgs[':min_date'] = gmdate('Y-m-d H:i:s', strtotime($dateRange[0]));
            $optionArgs[':max_date'] = gmdate('Y-m-d H:i:s', strtotime($dateRange[1]));
        }

        $whereProps = array();
        $modelIds = array();
        $topicJoin = false;
        foreach ($linePropArray as $i => $lineProps) {
            $modelIds[] = $lineProps['modelId'];
            if ($lineProps['filterType']=='topic') {
                $topicJoin = true;
                $whereProps[] = "normalised_topic=:topic$i";
                $optionArgs[':topic'.$i] = $lineProps['filterValue'];
            } elseif ($lineProps['filterType'] == 'text') {
//				if (strlen($lineProps['filterValue']) < 3) {
                $whereProps[] = "$textMatchColumn LIKE :text$i";
//					$args[':text' . $i] = '[[:<:]]'.$lineProps['filterValue'].'[[:>:]]';
                $optionArgs[':text' . $i] = '%'.$lineProps['filterValue'].'%';
//				} else {
//					$whereProps[] = "MATCH($textMatchColumn) AGAINST (:text$i)";
//					$args[':text' . $i] = $lineProps['filterValue'];
//				}
            }
        }

        if ($whereProps) {
            $options['where'][] = '('.implode(') OR (', $whereProps).')';
        }

        if ($search) {
            //parse special search filters
            $searchBits = explode(':', $search, 2);
            if (count($searchBits) > 1 && $searchBits[0] == 'date') {
                $dates = explode('/', $searchBits[1]);
                $options['where'][] = "created_time >= :filter_min_date";
                $options['where'][] = "created_time < :filter_max_date";
                $optionArgs[':filter_min_date'] = gmdate('Y-m-d H:i:s', strtotime($dates[0]));
                $optionArgs[':filter_max_date'] = gmdate('Y-m-d H:i:s', strtotime($dates[1]));
            } else {
                $textMatch = "$textMatchColumn LIKE :search";
                if ($statusType == 'tweet') {
                    $textMatch .= " OR user.name LIKE :search OR user.screen_name LIKE :search";
                } else {
                    $textMatch .= " OR actor.name LIKE :search";
                }
                $options['where'][] = $textMatch;
                $optionArgs[':search'] = "%$search%";
            }
        }

        if ($limit != -1) { $options['limit'] = $limit; }
        if ($offset != -1) { $options['offset'] = $offset; }

        list($args, $statusTable, $statusTableWhere) = self::getStatusTableQuery($modelIds);
        $args = array_merge($args, $optionArgs);

        $statusType = self::getStatusType();
        if ($statusType == 'tweet') {
            $ordering = isset($options['order']) ? $options['order'] : array('tweet_id'=>'ASC');
            $validOrderCols = array(
                'tweet'         => 'status.text_expanded',
                'tweet_id'      => 'status.tweet_id',
                'screen_name'   => 'user.screen_name',
                'user_name'     => 'user.name',
                'user_id'       => 'status.twitter_user_id',
                'date'          => 'status.created_time',
                'retweet_count' => 'status.retweet_count'
            );

            $distinct = count($modelIds) > 1 ? 'DISTINCT' : '';

            //can't select status.* as that will prevent DISTINCT from filtering out duplicate tweets
            $sql = "SELECT $distinct
					tweet_id, text_expanded, html_tweet, created_time, average_sentiment, retweet_count,
					user.name AS user_name, screen_name, profile_image_url
				FROM $statusTable AS status
				LEFT OUTER JOIN twitter_users AS user ON status.twitter_user_id = user.id";

            //only join on topics table if this is a topic query
            if ($topicJoin) {
                $sql .= " LEFT OUTER JOIN twitter_tweet_topics AS topic ON status.tweet_id = topic.twitter_tweet_id";
            }
        } else {
            $ordering = isset($options['order']) ? $options['order'] : array('post_id'=>'ASC');
            $validOrderCols = array(
                'message'       => 'status.message',
                'post_id'       => 'status.post_id',
                'date'          => 'status.created_time',
                'comments'      => 'status.comments',
                'likes'         => 'status.likes',
                'actor_name'    => 'actor.name'
            );

            $sql = "SELECT status.*, actor.type AS actor_type, actor.name AS actor_name, pic_url, profile_url
				FROM $statusTable AS status
				LEFT OUTER JOIN facebook_actors AS actor ON status.actor_id = actor.id";

            //only join on topics table if this is a topic query
            if ($topicJoin) {
                $sql .= " LEFT OUTER JOIN facebook_post_topics AS topic ON status . id = topic . facebook_stream_id";
            }
        }

        $sql .= " WHERE $statusTableWhere";

        if (isset($options['where'])) {
            $sql .= ' AND (' . implode(') AND (', $options['where']) . ')';
        }

        $sql .= self::generateOrderingString($ordering, $validOrderCols);

        if (isset($options['limit'])) {
            $limit = intval($options['limit']);
            $sql .= ' LIMIT '.$limit;
        } else {
            $limit = 0;
        }
        if (isset($options['offset'])) {
            $offset = intval($options['offset']);
            $sql .= ' OFFSET '.$offset;
        } else {
            $offset = 0;
        }

//		 echo $sql, "\n";
//		 print_r($args);
//		 die;
        $dataQuery = $db->prepare($sql);
        $dataQuery->execute($args);
        // $count = $db->query('SELECT FOUND_ROWS()')->fetchColumn();
        $data = $dataQuery->fetchAll(PDO::FETCH_OBJ);

        if ($limit && count($data) < $limit) {
            $count = $offset + count($data);
        } else {
            $count = 9999999;
        }
        return (object)array('count'=>$count, 'data'=>$data);
    }


}