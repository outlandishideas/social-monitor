<?php


use Facebook\FacebookRequestException;
use Facebook\GraphObject;
use Outlandish\SocialMonitor\FacebookApp;

class Provider_Facebook extends Provider_Abstract
{
	protected $connection = null;
    /**
     * @var FacebookApp
     */
    private $facebook;

    public function __construct(PDO $db, FacebookApp $facebook) {
		parent::__construct($db);
		$this->type = Enum_PresenceType::FACEBOOK();
        $this->tableName = 'facebook_stream';
        $this->facebook = $facebook;
    }

	public function fetchStatusData(Model_Presence $presence)
	{
		if (!$presence->getUID()) {
			throw new Exception('Presence not initialised/found');
		}

        // get all posts since the last time we fetched
		$stmt = $this->db->prepare("SELECT created_time
		    FROM {$this->tableName}
		    WHERE presence_id = :id
            AND created_time <= DATE_SUB(NOW(), INTERVAL 7 DAY)
		    ORDER BY created_time DESC
		    LIMIT 1");
		$stmt->execute(array(':id' => $presence->getId()));
		$since = $stmt->fetchColumn();
		if ($since) {
			$since = date_create_from_format("Y-m-d H:i:s", $since);
		} else {
            $since = null;
        }
		$posts = $this->facebook->pageFeed($presence->getUID(), $since);
        $count = 0;
        $this->parseAndInsertStatuses($presence, $posts, $count);
        //todo: update responses using the new api
//        $this->updateResponses($presence, $count);

        return $count;
	}

    /**
     * @param Model_Presence $presence
     * @param GraphObject $postData
     * @param $count
     */
    protected function parseAndInsertStatuses(Model_Presence $presence, GraphObject $postData, &$count)
	{
        $insertStmt = $this->db->prepare("
			INSERT INTO `{$this->tableName}`
			(`post_id`, `presence_id`, `message`, `created_time`, `actor_id`, `comments`,
				`likes`, `share_count`, `permalink`, `type`, `posted_by_owner`, `needs_response`, `in_response_to`)
			VALUES
			(:post_id, :presence_id, :message, :created_time, :actor_id, :comments,
				:likes, :share_count, :permalink, :type, :posted_by_owner, :needs_response, :in_response_to)
            ON DUPLICATE KEY UPDATE
                `likes` = VALUES(`likes`), `share_count` = VALUES(`share_count`), `comments` = VALUES(`comments`)
		");

        $count = 0;
        $duplicate = false;
        $links = array();
        while (!$duplicate) {
            /** @var GraphObject $posts */
            $posts = $postData->getPropertyAsArray('data');

            if (empty($posts)) {
                break;
            }

            /** @var GraphObject $post */
            foreach ($posts as $post) {
                $postArray = $post->asArray();
                $actorId = $postArray['from']->id;
                $createdTime = date_create_from_format(DateTime::ISO8601, $postArray['created_time']);
                $postedByOwner = $actorId == $presence->getUID();
                $args = array(
                    ':post_id' => $postArray['id'],
                    ':presence_id' => $presence->getId(),
                    ':message' => isset($postArray['message']) ? $postArray['message'] : null,
                    ':created_time' => gmdate("Y-m-d H:i:s", $createdTime->getTimestamp()),
                    ':actor_id' => $actorId,
                    ':comments' => $this->getCommentCount($post->getProperty('id')),
                    ':likes' => $this->getLikesCount($post->getProperty('id')),
                    ':share_count' => $this->getShareCount($post->getProperty('id')),
                    ':permalink' => isset($postArray['link']) ? $postArray['link'] : null,
                    ':type' => null,
                    ':posted_by_owner' => (int)$postedByOwner,
                    ':needs_response' => (int) (!$postedByOwner && isset($postArray['message'])),
                    ':in_response_to' => null
                );
                try {
                    $insertStmt->execute($args);
                } catch (PDOException $ex) {
                    if ($ex->getCode() == 23000) {
                        $duplicate = true;
                        break;
                    }
                    continue;
                } catch (Exception $ex) {
                    continue;
                }

                $id = $this->db->lastInsertId();
                if ($postedByOwner && isset($postArray['message']) && $postArray['message']) {
                    $links[$id] = $this->extractLinks($postArray['message']);
                }

                $count++;
            }

            $postData = $this->facebook->get($postData->getProperty('paging')->getProperty('next'));

        }

        $this->saveLinks('facebook', $links);
	}

    /**
     * Gets the count of the likes for the post id
     *
     * @param $postId
     * @return int
     */
    private function getLikesCount($postId)
    {
        try {
            /** @var GraphObject $likes */
            $likes = $this->facebook->postLikes($postId);
        } catch (FacebookRequestException $e) {
            return 0;
        }

        return $likes->getProperty('summary')->getProperty('total_count');
    }

    /**
     * Gets the comment count of the given post
     *
     * @param $postId
     * @return int
     */
    private function getCommentCount($postId)
    {
        try {
            /** @var GraphObject $comments */
            $comments = $this->facebook->postComments($postId);
        } catch (FacebookRequestException $e) {
            return 0;
        }

        if (!($comments instanceof GraphObject)) {
            return 0;
        }

        return $comments->getProperty('summary')->getProperty('total_count');
    }

    /**
     * Gets the share count for the given post
     *
     * @param $postId
     * @return int
     */
    private function getShareCount($postId)
    {
        /** @var GraphObject $shares */
        try {
            $shares = $this->facebook->postShares($postId);
        } catch (FacebookRequestException $e) {
            return 0;
        }
        $count = 0;

        while(true) {
            $shareArray = $shares->getPropertyAsArray('data');
            if (empty($shareArray)) {
                break;
            }

            $count += count($shareArray);

            try {
                $shares = $this->facebook->get($shares->getProperty('paging')->next);
            } catch (FacebookRequestException $e) {
                //break out of loop as we have no more shares to count
                break;
            }
        }

        return $count;
    }

    /**
     * updates Responses
     *
     * @param Model_Presence $presence
     * @param int $count
     */
    protected function updateResponses(Model_Presence $presence, &$count)
    {
        $postIds = $this->getUpdateableResponses($presence);

//        $count = 0;
        if ($postIds) {
            /** @var GraphObject $responses */
            $responses = $this->facebook->postResponses($postIds);

            $insertStmt = $this->db->prepare("
                INSERT INTO `{$this->tableName}`
                (`post_id`, `presence_id`, `message`, `created_time`, `actor_id`, `posted_by_owner`, `in_response_to`)
                VALUES
                (:post_id, :presence_id, :message, :created_time, :actor_id, :posted_by_owner, :in_response_to)
            ");

            /** @var GraphObject $post */
            foreach ($postIds as $postId) {
                $comments = $responses->getProperty($postId)->getPropertyAsArray('data');
                foreach ($comments as $post) {
                    $postArray = $post->asArray();
                    $actorId = $postArray['from']->id;
                    $createdTime = date_create_from_format(DateTime::ISO8601, $postArray['created_time']);
                    $args = array(
                        'post_id' => $postArray['id'],
                        'presence_id' => $presence->getId(),
                        'message' => isset($postArray['message']) ? $postArray['message'] : null,
                        'created_time' => gmdate("Y-m-d H:i:s", $createdTime->getTimestamp()),
                        'actor_id' => $actorId,
                        'posted_by_owner' => true,
                        'in_response_to' => $postArray['to']->data[0]->id
                    );

                    try {
                        $insertStmt->execute($args);
                    } catch (Exception $ex) {
                        continue;
                    }

                    $count++;
                }
            }

        }
    }

	public function getHistoricStream(Model_Presence $presence, \DateTime $start, \DateTime $end,
        $search = null, $order = null, $limit = null, $offset = null)
	{
        $clauses = array(
            'p.created_time >= :start',
            'p.created_time <= :end',
            'p.presence_id = :id',
            'p.in_response_to IS NULL' // response data are merged into the original posts
        );
        $args = array(
            ':start' => $start->format('Y-m-d H:i:s'),
            ':end'   => $end->format('Y-m-d H:i:s'),
            ':id'    => $presence->getId()
        );
        $searchArgs = $this->getSearchClauses($search, array('p.message'));
        $clauses = array_merge($clauses, $searchArgs['clauses']);
        $args = array_merge($args, $searchArgs['args']);

		$sql = "
			SELECT SQL_CALC_FOUND_ROWS p.*
			FROM {$this->tableName} AS p
			WHERE " . implode(' AND ', $clauses);
        $sql .= $this->getOrderSql($order, array('date'=>'created_time'));
        $sql .= $this->getLimitSql($limit, $offset);

        $stmt = $this->db->prepare($sql);
		$stmt->execute($args);
		$ret = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = $this->db->query('SELECT FOUND_ROWS()')->fetch(PDO::FETCH_COLUMN);

		// decorate the posts with actors, links and responses
        $postIds = array();
        $actorIds = array();
        $facebookIds = array();
        foreach ($ret as $post) {
            $postIds[] = $post['id'];
            $actorIds[] = $post['actor_id'];
            $facebookIds[] = $post['post_id'];
        }

        $links = $this->getLinks($postIds, 'facebook');
        $actors = $this->getActors($actorIds);
        $responses = $this->getResponses($presence->getId(), $facebookIds);

		foreach ($ret as &$r) {
            $id = $r['id'];
			$r['links'] = isset($links[$id]) ? $links[$id] : array();

            $facebookId = $r['post_id'];
			$r['first_response'] = isset($responses[$facebookId]) ? $responses[$facebookId] : array();

            $actorId = $r['actor_id'];
            $r['actor'] = isset($actors[$actorId]) ? $actors[$actorId] : new stdClass();
		}

		return (object)array(
            'stream' => count($ret) ? $ret : null,
            'total' => $total
        );
	}

    /**
     * Gets (object) data for the first response (if any) to the given posts, keyed by the originating post
     * @param $presenceId
     * @param $facebookIds
     * @return array
     */
    protected function getResponses($presenceId, $facebookIds)
    {
        $responses = array();
        if ($facebookIds) {
            $idString = array_map(function($a) { return "'" . $a . "'"; }, $facebookIds);
            $idString = implode(',', $idString);
            $stmt = $this->db->prepare("SELECT * FROM facebook_stream WHERE presence_id = :pid AND in_response_to IN ($idString)");
            $stmt->execute(array(':pid'=>$presenceId));
            foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $response) {
                $key = $response->in_response_to;
                if (!array_key_exists($key, $responses) || ($response->created_time < $responses[$key]->created_time)) {
                    $responses[$key] = $response;
                }
            }
        }
        return $responses;
    }

    /**
     * Gets (object) data for all of the given actor IDs. Some may be blank
     * @param $actorIds
     * @return array
     */
    protected function getActors($actorIds)
    {
        $actors = array();
        if ($actorIds) {
            $actorIdsString = implode(',', array_unique($actorIds));
            $stmt = $this->db->prepare("SELECT * FROM facebook_actors WHERE id IN ( $actorIdsString )");
            $stmt->execute();
            foreach($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
                $actors[$row->id] = $row;
            }
            // create blanks for any missing ones
            foreach ($actorIds as $id) {
                if (!isset($actors[$id])) {
                    $actors[$id] = (object)array(
                        'id' => $id,
                        'name' => '',
                        'profile_url' => ''
                    );
                }
            }
        }
        return $actors;
    }

	public function getHistoricStreamMeta(Model_Presence $presence, \DateTime $start, \DateTime $end, $ownPostsOnly = false)
	{
		$stmt = $this->db->prepare("
			SELECT
				posts.date AS date,
				posts.number_of_posts AS number_of_actions,
				links.number_of_links,
				links.number_of_bc_links
			FROM
				(
					SELECT
						DATE_FORMAT(created_time, '%Y-%m-%d') AS `date`,
						COUNT(*) AS `number_of_posts`
					FROM
						{$this->tableName}
					WHERE
						created_time >= :start
						AND created_time <= :end
						AND presence_id = :id
                        ".($ownPostsOnly ? 'AND posted_by_owner = 1' : '')."
					GROUP BY
						DATE_FORMAT(created_time, '%Y-%m-%d')
				) AS posts
				LEFT JOIN (
					SELECT
						DATE_FORMAT(p.created_time, '%Y-%m-%d') AS `date`,
						COUNT(sl.id) AS `number_of_links`,
						SUM(d.is_bc) AS `number_of_bc_links`
					FROM
						{$this->tableName} AS p
						LEFT JOIN status_links AS sl ON (p.id = sl.status_id AND sl.type = 'facebook')
						LEFT JOIN domains AS d ON (sl.domain = d.domain)
					WHERE
						p.created_time >= :start
						AND p.created_time <= :end
						AND p.presence_id = :id
                        ".($ownPostsOnly ? 'AND p.posted_by_owner = 1' : '')."
					GROUP BY

							DATE_FORMAT(p.created_time, '%Y-%m-%d')
				) AS links ON (posts.date = links.date)
			ORDER BY
				`date`
		");
		$stmt->execute(array(
			':id'		=> $presence->getId(),
			':start'	=> $start->format('Y-m-d H:i:s'),
			':end'	=> $end->format('Y-m-d H:i:s')
		));
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function update(Model_Presence $presence)
	{
		parent::update($presence);
        $presence->facebook_engagement = $this->calculateFacebookEngagement($presence);
	}

	protected function calculateFacebookEngagement(Model_Presence $presence)
	{
        $now = new DateTime();
        $then = clone $now;
        $then->modify("-1 week");

		$query = new Outlandish\SocialMonitor\FacebookEngagement\Query\WeightedFacebookEngagementQuery($this->db);
        $metric = new Outlandish\SocialMonitor\FacebookEngagement\FacebookEngagementMetric($query);

        return $metric->get($presence->getId(), $now, $then);
	}

	protected function getCommentsSharesLikes(Model_Presence $presence, DateTime $start, DateTime $end)
	{
		$args = array(
			':pid' => $presence->getId(),
			':start_time' => $start->format("Y-m-d"),
			':end_time' => $end->format("Y-m-d")
		);

		$sql = "
            SELECT
              `ph`.`created_time` AS `time`,
              IFNULL(SUM(`fs`.`comments`), 0) AS `comment_count`,
              IFNULL(SUM(`fs`.`likes`), 0) AS `like_count`,
              IFNULL(SUM(`fs`.`share_count`), 0) AS `share_count`,
              `ph`.`popularity`
            FROM (
                SELECT `presence_id`, DATE(`datetime`) as `created_time`, MAX(`value`) as `popularity`
                FROM `presence_history`
                WHERE `type` = 'popularity'
                  AND `presence_id` = :pid
                  AND `datetime` >= :start_time
                  AND `datetime` <= :end_time
                GROUP BY DATE(`datetime`)
            ) AS `ph`
            LEFT JOIN `facebook_stream` as `fs`
              ON DATE(`fs`.`created_time`) = `ph`.`created_time`
              AND `fs`.`presence_id` = `ph`.`presence_id`
            WHERE `ph`.`created_time` >= :start_time
              AND `ph`.`created_time` <= :end_time
            GROUP BY `ph`.`created_time`";

		$stmt = $this->db->prepare($sql);
		$stmt->execute($args);
		return $stmt->fetchAll(PDO::FETCH_OBJ);
	}

    private function extractLinks($message) {
        $links = array();
        if (preg_match_all('/[^\s]{5,}/', $message, $tokens)) {
            foreach ($tokens[0] as $token) {
                $token = trim($token, '.,;!"()');
                if (filter_var($token, FILTER_VALIDATE_URL)) {
                    try {
                        $links[] = $token;
                    } catch (RuntimeException $ex) {
                        // ignore failed URLs
                        $failedLinks[] = $token;
                    }
                }
            }
        }
        return $links;
    }

    public function updateMetadata(Model_Presence $presence) {

        try {
            $data = $this->facebook->pageInfo($presence->handle);
        } catch (FacebookRequestException $e) {
            $presence->uid = null;
            throw new Exception_FacebookNotFound('Facebook page not found: ' . $presence->handle, $e->getCode(), [], []);
        }

        $presence->type = $this->type;
        $presence->uid = $data->getProperty('id');
        $presence->name = $data->getProperty('name');
        $presence->page_url = $data->getProperty('link');
        $presence->popularity = $data->getProperty('likes');

        $this->updatePicture($presence);
	}

    protected function updatePicture(Model_Presence $presence)
    {
        try {
            $data = $this->facebook->pagePicture($presence->handle);
        } catch (FacebookRequestException $e) {
            return;
        }

        $presence->image_url = $data->getProperty('url');

    }

    /**
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return array
     */
    public function getResponseData(Model_Presence $presence, DateTime $start, DateTime $end)
    {
        $responseData = array();
        $clauses = array(
            'r.presence_id = :pid',
            't.created_time >= :start_date',
            't.created_time <= :end_date'
        );
        $args = array(
            ':pid'=>$presence->getId(),
            ':start_date' => $start->format('Y-m-d'),
            ':end_date' => $end->format('Y-m-d')
        );
        $stmt = $this->db->prepare("
          SELECT t.post_id as id, t.created_time as created, TIME_TO_SEC( TIMEDIFF( r.created_time, t.created_time ))/3600 AS time
          FROM {$this->tableName} AS t
            INNER JOIN {$this->tableName} AS r ON t.post_id = r.in_response_to
            WHERE " . implode(' AND ', $clauses) ."");
        $stmt->execute($args);
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $r) {
            $key = $r->id;
            if(!array_key_exists($key, $responseData)) {
                $responseData[$key] = (object)array('diff' => null, 'created' => null);
            }
            if (empty($responseData[$key]->diff) || $r->time < $responseData[$key]->diff) {
                $responseData[$key]->diff = $r->time;
                $responseData[$key]->created = $r->created;
            }
        }
        return $responseData;
    }

    /**
     * get ids of responses that need to be updateable
     *
     * @param Model_Presence $presence
     * @return array
     */
    protected function getUpdateableResponses(Model_Presence $presence)
    {
        $presenceId = $presence->getId();

        // update the responses for any non-page posts that don't have a response yet.
        // Only get those that explicitly need one, or were posted in the last 3 days
        $args = array(
            ':id' => $presenceId,
            ':necessary_since' => date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' -30 days')),
            ':unnecessary_since' => date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' -3 days'))
        );
        $stmt = $this->db->prepare("SELECT DISTINCT a.post_id
            FROM (
                SELECT *
                FROM {$this->tableName}
                WHERE presence_id = :id
                AND in_response_to IS NULL
                AND
                (
                    (
                      needs_response = 1
                      AND created_time > :necessary_since
                    )
                    OR
                    (
                      posted_by_owner = 0
                      AND message <> ''
                      AND message IS NOT NULL
                      AND created_time > :unnecessary_since
                    )
                )
            ) as a
            LEFT OUTER JOIN {$this->tableName} AS b
                ON b.presence_id = a.presence_id
                AND b.in_response_to = a.post_id
            WHERE b.id IS NULL");
        $stmt->execute($args);
        $postIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return $postIds;
    }


}