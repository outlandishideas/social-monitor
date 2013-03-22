<?php

// base class for facebook pages and twitter lists/searches
abstract class Model_SocialApiBase extends Model_Base {

	// number of seconds in each pre-calculated bucket
	public static $bucketSizes = array(
		'bucket_half_hour' => 1800, // 30*60
		'bucket_4_hours' => 14400, // 4*60*60
		'bucket_12_hours' => 43200, // 12*60*60
		'bucket_day' => 86400 // 24*60*60
	);

	public function getCampaign() {
		$this->campaign = Model_Campaign::fetchById($this->campaign_id);
		return $this->campaign;
	}

	public static function getStatusType() {
		$classname = get_called_class();
		return (strpos($classname, 'Twitter') !== false ? 'tweet' : 'post');
	}
	
	// gets the appropriate status (tweet/post) table and corresponding where clause(s)
	protected static function getStatusTableQuery($modelIds) {
		$statusType = self::getStatusType();
		if ($statusType == 'tweet') {
			$classname = get_called_class();
			if ($classname == 'Model_TwitterSearch') {
				$parentType = 'search';
			} else {
				$parentType = 'list';
			}
			$table = 'twitter_tweets';
		} else {
			$parentType = 'page';
			$table = 'facebook_stream';
		}
		
		if (!is_array($modelIds)) {
			$modelIds = array($modelIds);
		}

		$args = array();
		$modelPlaceholders = array();
		foreach ($modelIds as $index=>$id) {
			$placeholder = ":{$parentType}_{$index}";
			$args[$placeholder] = $id;
			$modelPlaceholders[] = $placeholder;
		}
		$modelIds = implode(',', $modelPlaceholders);
		if ($statusType == 'tweet') {
			$where = "parent_type = :parent_type AND parent_id IN ($modelIds)";
			$args[':parent_type'] = $parentType;
		} else {
			$where = "facebook_page_id IN ($modelIds)";
		}
		
		return array($args, $table, $where);
	}
	
	public static function getGroupedTopicsForModelIds($db, $modelIds, $dateRange = null, $search = null, $ordering = array(), $limit = -1, $offset = -1) {
		if (!$modelIds) {
			return array();
		}

		$statusType = self::getStatusType();
		
		list($args, $statusTable, $statusTableWhere) = self::getStatusTableQuery($modelIds);
		$args[':range_start'] = gmdate('Y-m-d H:i:s', strtotime($dateRange[0]));
		$args[':range_end'] = gmdate('Y-m-d H:i:s', strtotime($dateRange[1]));

		if ($search) {
			$searchSql = 'AND topics.normalised_topic LIKE :search';
			$args[':search'] = "%$search%";
		} else {
			$searchSql = '';
		}
		
		$suffix = '';
		if ($limit >= 0) {
			$suffix .= " LIMIT $limit";
		}
		if ($offset >= 0) {
			$suffix .= " OFFSET $offset";
		}

		$sql = "SELECT topic AS text, normalised_topic, COUNT(*) AS mentions, AVG(polarity) AS polarity, AVG(importance) AS importance
			FROM $statusTable AS status";
		if ($statusType == 'tweet') {
			$sql .= "\nINNER JOIN twitter_tweet_topics AS topics ON topics.twitter_tweet_id = status.tweet_id";
		} else {
			$sql .= "\nINNER JOIN facebook_post_topics AS topics ON topics.facebook_stream_id = status.id";
		}
		$sql .= "\nWHERE $statusTableWhere
			AND status.created_time BETWEEN :range_start AND :range_end
			$searchSql
			GROUP BY normalised_topic";
			
		$validOrderCols = array(
			'topic'      => 'normalised_topic', 
			'mentions'   => 'mentions', 
			'polarity'   => 'polarity', 
			'importance' => 'importance'
		);
		$order = self::generateOrderingString($ordering, $validOrderCols);
		if (!$order) {
			$order = self::generateOrderingString(array('mentions'=>'DESC'), $validOrderCols);
		}
		$sql .= $order;

		// echo $sql, "\n", $suffix, "\n";
		// print_r($args);
		// die;
		
		$dataStatement = $db->prepare("$sql $suffix");
		$dataStatement->execute($args);

		$groupedTopics = $dataStatement->fetchAll(PDO::FETCH_OBJ);
		
		if ($limit && count($groupedTopics) < $limit) {
			$count = max($offset, 0) + count($groupedTopics);
		} else {
			$count = 99999999;
		}
		
		return (object)array('count'=>$count, 'data'=>$groupedTopics);
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

	public function recalculateBuckets($offset = 0) {
		$update = "bucket_half_hour = from_unixtime(unix_timestamp(created_time) - (unix_timestamp(created_time) + :offset)%1800),
			bucket_4_hours = from_unixtime(unix_timestamp(created_time) - (unix_timestamp(created_time) + :offset)%14400),
			bucket_12_hours = from_unixtime(unix_timestamp(created_time) - (unix_timestamp(created_time) + :offset)%43200),
			bucket_day = from_unixtime(unix_timestamp(created_time) - (unix_timestamp(created_time) + :offset)%86400)";

		if (get_class($this) == 'Model_FacebookPage') {
			$sql = "UPDATE facebook_stream SET $update WHERE facebook_page_id = :id";
		} elseif (get_class($this) == 'Model_TwitterSearch') {
			$sql = "UPDATE twitter_tweets SET $update WHERE parent_type = 'search' AND parent_id = :id";
		} else {
			$sql = "UPDATE twitter_tweets SET $update WHERE parent_type = 'list' AND parent_id = :id";
		}

		$statement = $this->_db->prepare($sql);
		$statement->execute(array(':id'=>$this->id, ':offset'=>$offset));
	}
}