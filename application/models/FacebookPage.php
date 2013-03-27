<?php

include APP_ROOT_PATH."/lib/facebook/facebook.php";

class Model_FacebookPage extends Model_SocialApiBase {
	protected $_tableName = 'facebook_pages';

	private static $_fb;

    public function getFacebookPosts() {
        $this->facebookPosts = Model_FacebookPost::fetchAll('facebook_page_id = '. $this->id);
    }

	/**
	 * Update page info from FB
	 */
	public function updateInfo() {
		$fql = 'SELECT page_id, name, username, pic_square, page_url, fan_count
			FROM page WHERE username = "' . $this->username.'"';
		$data = $this->facebookQuery($fql);
		if (!$data) {
			throw new RuntimeException('Facebook page not found: ' . $this->username);
		}
		$this->fromArray($data[0]);
		$this->last_updated = gmdate('Y-m-d H:i:s');
	}

	public function countPostsSince($date) {
		$statement = $this->_db->prepare("SELECT COUNT(*) FROM facebook_stream
			WHERE facebook_page_id = :id
			AND created_time > :date");

		$statement->execute(array(':id' => $this->id, ':date' => $date));
		return $statement->fetchColumn();
	}

	/**
	 * Fetch posts (and their comments) for page from FB
	 * @return int number of posts inserted
	 */
	public function fetchPosts() {
		if ($this->last_fetched) {
			//page has been fetched before
			$lastFetchedTimestamp = strtotime($this->last_fetched . ' UTC');
			$updatedClauses = array(
				//fetch posts changed up to an hour before last fetch
				' AND updated_time > ' . ($lastFetchedTimestamp - 3600),
				//fetch posts created 24 hours ago
				' AND created_time > ' . ($lastFetchedTimestamp - 3600 * 24) . ' AND created_time < ' . (time() - 3600 * 24),
				//fetch posts created 7 days ago
				' AND created_time > ' . ($lastFetchedTimestamp - 3600 * 24 * 7) . ' AND created_time < ' . (time() - 3600 * 24 * 7)

			);
		} else {
			//this is a new page so fetch all posts
			$updatedClauses = array('');
		}

		$shouldAnalyse = $this->should_analyse && $this->campaign->analysis_quota;
		$localTimeZone = new DateTimeZone($this->campaign->timezone);
		$fetchCount = new FetchCount(0, 0, 'post');

		//repeat queries for each time frame
		foreach ($updatedClauses as $updatedClause) {
			$postsFql = 'SELECT post_id, message, created_time, actor_id, comments, likes, permalink, type
				FROM stream WHERE source_id = ' . $this->page_id . $updatedClause;
			$commentsFql = 'SELECT id, post_id, fromid, time, text, likes
				FROM comment WHERE post_id IN
				(SELECT post_id FROM stream WHERE source_id = '.$this->page_id . " AND comments.count > 0 $updatedClause)";

			$postData = $this->facebookQuery($postsFql);
			if ($postData) {
				$fetchCount->fetched += count($postData);
				foreach ($postData as $post) {
					$p = Model_FacebookPost::fetchBy('post_id', $post['post_id']);
					if (!$p) {

						$p = new Model_FacebookPost(array(
							'facebook_page_id' => $this->id,
							'post_id' => $post['post_id'],
							'message' => $post['message'],
							'created_time' => gmdate('Y-m-d H:i:s', $post['created_time']),
							'actor_id' => $post['actor_id'],
							'permalink' => $post['permalink'],
							'type' => $post['type'],
							'is_analysed' => !$shouldAnalyse
						));

						$date = DateTime::createFromFormat('U', $post['created_time']);
						foreach (self::$bucketSizes as $bucket => $size) {
							$bucketStart = $post['created_time'] - ($post['created_time'] + $localTimeZone->getOffset($date)) % $size;
							$p->{$bucket} = gmdate('Y-m-d H:i:s', $bucketStart);
						}
						if (!$p->type) {
							$p->type = 0;
						}
						$fetchCount->added++;
					} else if (!$shouldAnalyse) {
						$p->is_analysed = true;
					}
					$p->comments = isset($post['comments']['count']) ? $post['comments']['count'] : 0;
					$p->likes = isset($post['likes']['count']) ? $post['likes']['count'] : 0;
					$p->save();
				}
			}

			//fetch comments
			$commentData = $this->facebookQuery($commentsFql);
			if ($commentData) {
				foreach ($commentData as $comment) {
					$c = Model_FacebookComment::fetchBy('comment_id', $comment['id']);
					if (!$c) {
						$c = new Model_FacebookComment();
						$c->comment_id = $comment['id'];
						$c->post_id = $comment['post_id'];
						$c->fromid = $comment['fromid'];
						$c->time = gmdate('Y-m-d H:i:s', $comment['time']);
						$c->text = $comment['text'];
					}
					$c->likes = $comment['likes'];
					$c->save();
				}
			}
		}

		// fetch any actors that are currently unfetched or old
		$actorQuery = $this->_db->prepare('SELECT DISTINCT actor_id FROM
			((SELECT actor_id FROM facebook_stream) UNION (SELECT fromid AS actor_id FROM facebook_comments)) AS ids
			LEFT OUTER JOIN facebook_actors AS actors ON ids.actor_id = actors.id
			WHERE actors.last_fetched IS NULL OR actors.last_fetched < NOW() - INTERVAL ' . Zend_Registry::get('config')->facebook->cache_user_data . ' DAY');
		$actorQuery->execute();
		$actorIds = $actorQuery->fetchAll(PDO::FETCH_COLUMN);
		if ($actorIds) {
			$fbUsers = $this->keyedFacebookQuery('SELECT uid, username, name, pic_square, profile_url FROM user', 'uid', $actorIds);
			$fbPages = $this->keyedFacebookQuery('SELECT page_id, username, name, pic_square FROM page', 'page_id', $actorIds);
			$fbGroups = $this->keyedFacebookQuery('SELECT gid, name, pic_small FROM group', 'gid', $actorIds);
			$fbEvents = $this->keyedFacebookQuery('SELECT eid, name, pic_square FROM event', 'eid', $actorIds);
			
			$insertActor = $this->_db->prepare('REPLACE INTO facebook_actors (id, username, name, pic_url, profile_url, type, last_fetched) VALUES (?, ?, ?, ?, ?, ?, ?)');
			$now = gmdate('Y-m-d H:i:s');
			foreach ($actorIds as $id) {
				$toInsert = array($id);
				if (array_key_exists($id, $fbUsers)) {
					$item = $fbUsers[$id];
					$toInsert[] = $item['username'];
					$toInsert[] = $item['name'];
					$toInsert[] = $item['pic_square'];
					$toInsert[] = $item['profile_url'];
					$toInsert[] = 'user';
				} else if (array_key_exists($id, $fbPages)) {
					$item = $fbPages[$id];
					$toInsert[] = $item['username'];
					$toInsert[] = $item['name'];
					$toInsert[] = $item['pic_square'];
					$toInsert[] = null;
					$toInsert[] = 'page';
				} else if (array_key_exists($id, $fbGroups)) {
					$item = $fbGroups[$id];
					$toInsert[] = null;
					$toInsert[] = $item['name'];
					$toInsert[] = $item['pic_small'];
					$toInsert[] = null;
					$toInsert[] = 'group';
				} else if (array_key_exists($id, $fbEvents)) {
					$item = $fbEvents[$id];
					$toInsert[] = null;
					$toInsert[] = $item['name'];
					$toInsert[] = $item['pic_square'];
					$toInsert[] = null;
					$toInsert[] = 'group';
				} else {
					$toInsert[] = null;
					$toInsert[] = null;
					$toInsert[] = null;
					$toInsert[] = null;
					$toInsert[] = 'unknown';
				}
				$toInsert[] = $now;
				$insertActor->execute($toInsert);
			}
		}
		
		return $fetchCount;
	}

	private function keyedFacebookQuery($query, $idCol, $ids) {
		$items = $this->facebookQuery($query . ' WHERE ' . $idCol . ' IN (' . implode(',', $ids) . ')');
		$keyedItems = array();
		if ($items) {
			foreach ($items as $item) {
				$keyedItems[$item[$idCol]] = $item;
			}
		}
		return $keyedItems;
	}
	
	/**
	 * Query Facebook API
	 * @param $fql string FQL query string
	 * @return mixed
	 */
	public function facebookQuery($fql) {
		$fb = self::fb();
		$ret = $fb->api( array(
		     'method' => 'fql.query',
		     'query' => $fql,
		 ));
		return $ret;
	}

	/**
	 * @static
	 * @return mixed instance of Facebook API object
	 */
	public static function fb() {

		if (!isset(self::$_fb)) {
			$config = Zend_Registry::get('config');
			self::$_fb = new Facebook(array(
				'appId' => $config->facebook->appId,
				'secret' => $config->facebook->secret
			));
		}

		return self::$_fb;
	}
	
	public function delete() {
		$args = array(':page_id'=>$this->id);
		// delete comments, then posts, then topics, then the page
		$deleteCommentsQuery = $this->_db->prepare('DELETE FROM facebook_comments WHERE post_id IN
			(SELECT post_id FROM facebook_stream WHERE facebook_page_id = :page_id)');
		$deleteCommentsQuery->execute($args);
		$deleteTopicsQuery = $this->_db->prepare('DELETE FROM facebook_post_topics WHERE facebook_stream_id IN
			(SELECT id FROM facebook_stream WHERE facebook_page_id = :page_id)');
		$deleteTopicsQuery->execute($args);
		$deleteStreamQuery = $this->_db->prepare('DELETE FROM facebook_stream WHERE facebook_page_id = :page_id');
		$deleteStreamQuery->execute($args);
		parent::delete();
	}

	public static function fetchUnanalysed($campaign, $limit) {
		$instance = new static();

		$sql = "SELECT fs.*
			FROM facebook_stream fs
			JOIN {$instance->_tableName} fp ON fs.facebook_page_id = fp.id
			WHERE is_analysed = 0
			AND created_time > DATE_SUB(NOW(), INTERVAL 2 DAY)
			AND fp.campaign_id = :campaign_id
			ORDER BY created_time DESC LIMIT $limit";

		$statement = $instance->_db->prepare($sql);
		$statement->execute(array(':campaign_id' => $campaign->id));
		return Model_FacebookPost::objectify($statement);
	}

	/**
	 * @param $db PDO
	 * @param $modelIds
	 * @param $dateRange
	 * @param null $filterType
	 * @param null $filterValue
	 * @param string $bucketCol
	 * @return array
	 */
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
				INNER JOIN facebook_post_topics AS topics ON topics.facebook_stream_id = status.id
				WHERE topics.normalised_topic = :topic
				AND $statusTableWhere
				AND status.created_time BETWEEN :range_start AND :range_end
				GROUP BY date";
			$args[':topic'] = $filterValue;
		} elseif ($filterType == 'text') {
			$sql = "SELECT COUNT(*) AS mentions, IFNULL(average_sentiment, 0) AS polarity, $bucketCol AS date
				FROM $statusTable AS stream
				WHERE stream.created_time BETWEEN :range_start AND :range_end
				AND $statusTableWhere
				AND message LIKE :text
				GROUP BY date";
			$args[':text'] = '%'.$filterValue.'%';
		} else {
			$sql = "SELECT COUNT(*) AS mentions, IFNULL(average_sentiment, 0) AS polarity, $bucketCol AS date
				FROM $statusTable AS stream
				WHERE stream.created_time BETWEEN :range_start AND :range_end
				AND $statusTableWhere
				GROUP BY date";
		}

		try {
			$statement = $db->prepare($sql);
			$statement->execute($args);

			return $statement->fetchAll(PDO::FETCH_ASSOC);
		} catch (Exception $ex) {
			echo $sql, "\n", print_r($args, true), "\n", $ex->getMessage();
			die;
		}
	}
}