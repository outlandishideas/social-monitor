<?php


use Outlandish\SocialMonitor\Adapter\InstagramAdapter;
use Outlandish\SocialMonitor\Database\Database;
use Outlandish\SocialMonitor\Engagement\EngagementScore;
use Outlandish\SocialMonitor\Models\InstagramStatus;
use Outlandish\SocialMonitor\Models\Status;
use Outlandish\SocialMonitor\PresenceType\PresenceType;

class Provider_Instagram extends Provider_Abstract
{
	protected $connection = null;

    public function __construct(Database $db, InstagramAdapter $adapter, PresenceType $type) {
		parent::__construct($db, $adapter, $type, 'instagram_stream');
    }

	public function fetchStatusData(Model_Presence $presence)
	{
        $count = 0;
		if (!$presence->getUID()) {
			throw new Exception('Presence not initialised/found');
		}

        // get all posts since the last time we fetched
        $stmt = $this->db->prepare("SELECT post_id
		    FROM {$this->tableName}
		    WHERE presence_id = :id
            AND created_time <= DATE_SUB(NOW(), INTERVAL 7 DAY)
		    ORDER BY created_time DESC
		    LIMIT 1");
        $stmt->execute(array(':id'=>$presence->getId()));
        $lastPostId = $stmt->fetchColumn();

        $posts = $this->adapter->getStatuses($presence->getUID(), $lastPostId, null);

        $this->insertStatuses($presence, $posts, $count);

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
			(`post_id`, `presence_id`, `message`, `created_time`, `comments`,
				`likes`, `permalink`, `image_url`)
			VALUES
			(:post_id, :presence_id, :message, :created_time, :comments,
				:likes, :permalink, :image_url)
            ON DUPLICATE KEY UPDATE
                `likes` = VALUES(`likes`), `share_count` = VALUES(`share_count`), `comments` = VALUES(`comments`)
		");

        $count = 0;

        /** @var InstagramStatus $post */
        foreach ($posts as $post) {
            $args = array(
                ':post_id' => $post->id,
                ':presence_id' => $presence->getId(),
                ':message' => $post->message,
                ':created_time' => gmdate('Y-m-d H:i:s', $post->created_time),
                ':comments' => $post->comments,
                ':likes' => $post->likes,
                ':permalink' => $post->permalink,
                ':image_url' => $post->image_url
            );
            try {
                $result = $insertStmt->execute($args);
                if(!$result) {
                    $error = $insertStmt->errorInfo();
                    error_log('Error inserting instagram status: '.$error[2]);
                }
            } catch (PDOException $ex) {
                if ($ex->getCode() == 23000) {
                    continue;
                }
                continue;
            } catch (Exception $ex) {
                continue;
            }
            $count++;
        }
	}

	public function getHistoricStreamMeta(Model_Presence $presence, \DateTime $start, \DateTime $end, $ownPostsOnly = false)
	{
		$stmt = $this->db->prepare("
			SELECT
				posts.date AS date,
				posts.number_of_posts AS number_of_actions
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
					GROUP BY
						DATE_FORMAT(created_time, '%Y-%m-%d')
				) AS posts
			ORDER BY
				`date`
		");
		$stmt->execute(array(
			':id'		=> $presence->getId(),
			':start'	=> $start->format('Y-m-d H:i:s'),
			':end'	=> $end->format('Y-m-d H:i:s')
		));
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function update(Model_Presence $presence)
	{
		parent::update($presence);
        $presence->instagram_engagement = $this->calculateInstagramEngagement($presence);
    }

    public function calculateInstagramEngagement(Model_Presence $presence)
    {
        $now = new DateTime();
        $then = clone $now;
        $then->modify("-1 week");

        $query = new Outlandish\SocialMonitor\Engagement\Query\WeightedInstagramEngagementQuery($this->db);
        $metric = new Outlandish\SocialMonitor\Engagement\EngagementMetric($query);

        return $metric->get($presence->getId(), $now, $then);
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
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return array
     */
    public function getResponseData(Model_Presence $presence, DateTime $start, DateTime $end)
    {
        $responseData = array();
        /*$clauses = array(
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
        foreach ($stmt->fetchAll(\PDO::FETCH_OBJ) as $r) {
            $key = $r->id;
            if(!array_key_exists($key, $responseData)) {
                $responseData[$key] = (object)array('diff' => null, 'created' => null);
            }
            if (empty($responseData[$key]->diff) || $r->time < $responseData[$key]->diff) {
                $responseData[$key]->diff = $r->time;
                $responseData[$key]->created = $r->created;
            }
        }*/
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
        $postIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        return $postIds;
    }

    protected function parseStatuses($raw)
    {
        if(!$raw || !count($raw)) {
            return [];
        }
        $parsed = array();
        foreach ($raw as $r) {
            $status = new Status();
            $status->id = $r['id'];
            $status->message = $r['message'] . ' <img src="' . $r['image_url'] . '">';
            $status->created_time = $r['created_time'];
            $status->permalink = $r['permalink'];
            $presence = Model_PresenceFactory::getPresenceById($r['presence_id']);
            $status->presence_id = $r['presence_id'];
            $status->presence_handle = $presence->getHandle();
            $status->presence_type = 'instagram';
            $status->presence_name = $presence->getName();
            $status->engagement = [
                'comments' => $r['comments'],
                'likes' => $r['likes'],
                'comparable' => (($r['likes'] + $r['comments'] * 4) / 5)
            ];
            $status->icon = $this->type->getSign();
            $parsed[] = (array)$status;
        }
        return $parsed;
    }


}