<?php

use Outlandish\SocialMonitor\Adapter\FacebookAdapter;
use Outlandish\SocialMonitor\Engagement\EngagementScore;
use Outlandish\SocialMonitor\Models\FacebookStatus;
use Outlandish\SocialMonitor\Engagement\EngagementMetric;
use Outlandish\SocialMonitor\Models\Status;
use Outlandish\SocialMonitor\PresenceType\PresenceType;

class Provider_Facebook extends Provider_Abstract
{
	protected $connection = null;
    private $engagementMetric;

    public function __construct(PDO $db, FacebookAdapter $adapter, EngagementMetric $metric, PresenceType $type) {
		parent::__construct($db, $adapter, $type, 'facebook_stream');
        $this->engagementMetric = $metric;
        $this->engagementStatement = '(likes + comments * 4 + share_count * 7)';
    }

	public function fetchStatusData(Model_Presence $presence)
	{
		if (!$presence->getUID()) {
			throw new Exception('Presence not initialised/found');
		}

        // get all posts since the last time we fetched
        // todo: this isn't right. It should get the last one from seven days ago, or the last one ever
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

        $posts = $this->adapter->getStatuses($presence->getUID(), $since);

        $this->insertStatuses($presence, $posts, $count);
        //todo: update responses using the new api
        $this->updateResponses($presence, $count);

        return $count;
	}

    /**
     * @param Model_Presence $presence
     * @param array          $posts
     * @param mixed          $count
     */
    protected function insertStatuses(Model_Presence $presence, array $posts, &$count)
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
        $links = array();

        /** @var FacebookStatus $post */
        foreach ($posts as $post) {
            $args = array(
                ':post_id' => $post->id,
                ':presence_id' => $presence->getId(),
                ':message' => $post->message,
                ':created_time' => $post->created_time,
                ':actor_id' => $post->actor_id,
                ':comments' => $post->comments,
                ':likes' => $post->likes,
                ':share_count' => $post->share_count,
                ':permalink' => $post->permalink,
                ':type' => $post->type,
                ':posted_by_owner' => (int) $post->posted_by_owner,
                ':needs_response' => (int) $post->needs_response,
                ':in_response_to' => null
            );
            try {
                $result = $insertStmt->execute($args);
                if(!$result) {
                    error_log(join(',',$insertStmt->errorInfo()));
                }
            } catch (PDOException $ex) {
                if ($ex->getCode() == 23000) {
                    continue;
                }
                continue;
            } catch (Exception $ex) {
                continue;
            }

            $id = $this->db->lastInsertId();
            if ($post->links && !empty($post->links)) {
                $links[$id] = $post->links;
            }

            $count++;
        }

        $this->saveLinks('facebook', $links);
	}

    /**
     * updates Responses
     *
     * @param Model_Presence $presence
     * @param int $count
     * @throws Exception
     */
    protected function updateResponses(Model_Presence $presence, &$count)
    {
        $postIds = $this->getUpdateableResponses($presence);

        if ($postIds) {

            /** @var FacebookStatus[] $responses */
            $responses = $this->adapter->getResponses($postIds,$presence->getUID());

            $insertStmt = $this->db->prepare("
                INSERT INTO `{$this->tableName}`
                (`post_id`, `presence_id`, `message`, `created_time`, `actor_id`, `posted_by_owner`, `in_response_to`)
                VALUES
                (:post_id, :presence_id, :message, :created_time, :actor_id, :posted_by_owner, :in_response_to)
            ");

            /** @var FacebookStatus $response */
            foreach($responses as $response) {
                $args = array(
                    'post_id' => $response->id,
                    'presence_id' => $presence->getId(),
                    'message' => $response->message,
                    'created_time' => $response->created_time,
                    'actor_id' => $response->actor_id,
                    'posted_by_owner' => $response->posted_by_owner,
                    'in_response_to' => $response->in_response_to_status_uid
                );

                try {
                    $inserted = $insertStmt->execute($args);
                    if ($inserted) {
                        $count++;
                    }

                } catch (Exception $ex) {
                    //do nothing
                    echo "Could not insert response: " . json_encode($args) . PHP_EOL;
                }

            }
        }
    }

	public function getHistoricStream(Model_Presence $presence, \DateTime $start, \DateTime $end,
        $search = null, $order = null, $limit = null, $offset = null)
	{
        $presenceId = $presence->getId();
        $clauses = array(
            'p.created_time >= :start',
            'p.created_time <= :end',
            'p.presence_id = :id',
            'p.in_response_to IS NULL' // response data are merged into the original posts
        );
        $args = array(
            ':start' => $start->format('Y-m-d H:i:s'),
            ':end'   => $end->format('Y-m-d H:i:s'),
            ':id' => $presenceId
        );
        return $this->getHistoricStreamData($clauses,$args,$search,$order,$limit,$offset);
	}

    public function getHistoricStreamMulti($presences, \DateTime $start, \DateTime $end,
                                           $search = null, $order = null, $limit = null, $offset = null)
    {
        $clauses = array(
            "p.$this->createdTimeColumn >= :start",
            "p.$this->createdTimeColumn <= :end",
            'p.in_response_to IS NULL' // response data are merged into the original posts
        );
        $args = array(
            ':start' => $start->format('Y-m-d H:i:s'),
            ':end'   => $end->format('Y-m-d H:i:s'),
        );
        if($presences && count($presences)) {
            $ids = array_map(function($p) {
                return $p->getId();
            },$presences);
            $clauses[] = 'p.presence_id IN ('.implode($ids,',') .')';
        }

        return $this->getHistoricStreamData($clauses,$args,$search,$order,$limit,$offset);
    }

    protected function decorateStreamData(&$ret) {
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

        foreach ($ret as &$r) {
            $id = $r['id'];
            $responses = $this->getResponses($r['presence_id'], [$id]);

            $r['links'] = isset($links[$id]) ? $links[$id] : array();

            $facebookId = $r['post_id'];
            $r['first_response'] = isset($responses[$facebookId]) ? $responses[$facebookId] : array();

            $actorId = $r['actor_id'];
            $r['actor'] = isset($actors[$actorId]) ? $actors[$actorId] : new stdClass();
        }
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

	public function calculateFacebookEngagement(Model_Presence $presence)
	{
        $now = new DateTime();
        $then = clone $now;
        $then->modify("-1 week");

        return $this->engagementMetric->get($presence->getId(), $now, $then);
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

    public function updateMetadata(Model_Presence $presence) {

        try {
            $metadata = $this->adapter->getMetadata($presence->handle);
        } catch (Exception_FacebookNotFound $e) {
            $presence->uid = null;
            throw $e;
        }

        $presence->type = $this->type;
		$presence->updateFromMetadata($metadata);
	}

    /**
     * Get response time data for all British Council statuses that are a response to another status
     *
     * Statuses represent both Facebook posts and Facebook comments. This method gets response time
     * data for all statuses belonging to the $presence and occurring between $start and $end.
     *
     * The data returned is array of stdClass objects that hold the created time for the response and the
     * number of seconds between the original status and the response.
     *
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
            't.created_time <= :end_date',
            'r.posted_by_owner = 1' // ensures that only british council posts are returned
        );
        $args = array(
            ':pid'=>$presence->getId(),
            ':start_date' => $start->format('Y-m-d'),
            ':end_date' => $end->format('Y-m-d')
        );

        $clauseString = implode(' AND ', $clauses);

        $sql = "
          SELECT
            t.post_id as id,
            t.created_time as created,
            TIME_TO_SEC( TIMEDIFF( r.created_time, t.created_time ))/3600 AS `time`
          FROM
            {$this->tableName} AS t
          INNER JOIN
            {$this->tableName} AS r ON t.post_id = r.in_response_to
          WHERE {$clauseString};";
        $stmt = $this->db->prepare($sql);
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

        $sql = "SELECT
                  DISTINCT a.post_id
                  FROM (
                    SELECT *
                    FROM {$this->tableName}
                    WHERE presence_id = :id
                    AND
                      (
                        ( needs_response = 1 AND created_time > :necessary_since )
                        OR
                        ( message <> '' AND message IS NOT NULL AND created_time > :unnecessary_since )
                      )
                  ) as a
                  LEFT OUTER JOIN {$this->tableName} AS b
                  ON b.presence_id = a.presence_id
                  AND b.in_response_to = a.post_id
                  WHERE b.id IS NULL;";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($args);

        $postIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return $postIds;
    }

    /**
     * @param Model_Presence $presence
     * @return EngagementScore
     */
    public function getEngagementScore($presence)
    {
        return new EngagementScore('Facebook engagement score', 'facebook', $presence->getEngagementScoreRaw(true));
    }

    protected function parseStatuses($raw)
    {
        if(!$raw || !count($raw)) {
            return [];
        }
        $parsed = array();
        foreach ($raw as $r) {

            if (array_key_exists('first_response',$r)) {
                $response = $r['first_response']->message;
                $responseDate = $r['first_response']->created_time;
            } else {
                $response = null;
                $responseDate = gmdate('Y-m-d H:i:s');
            }

            $timeDiff = strtotime($responseDate) - strtotime($r['created_time']);
            $components = array();
            $timeDiff /= 60;
            $elements = array(
                'minute' => 60,
                'hour' => 24,
                'day' => 100000
            );
            foreach ($elements as $label => $size) {
                $val = $timeDiff % $size;
                $timeDiff /= $size;
                if ($val) {
                    array_unshift($components, $val . ' ' . $label . ($val == 1 ? '' : 's'));
                }
            }

            $status = new Status();
            $status->id = $r['id'];
            $status->message = $r['message'];
            $status->created_time = $r['created_time'];
            $status->permalink = $r['permalink'];
            $presence = Model_PresenceFactory::getPresenceById($r['presence_id']);
            $status->presence_id = $r['presence_id'];
            $status->presence_name = $presence->getName();
            $status->engagement = [
                'shares' => $r['share_count'],
                'likes' => $r['likes'],
                'comments' => $r['comments'],
                'comparable' => (($r['likes'] + $r['comments'] * 4 + $r['share_count'] * 7) / 12)
            ];
            $status->icon = $this->type->getSign();
            $status->needs_response = $r['needs_response'];
            $status->first_response = array(
                'message' => $response,
                'date' => Model_Base::shortDate($responseDate),
                'date_diff' => implode(', ', $components),
            );

            $parsed[] = (array)$status;
        }
        return $parsed;
    }
}