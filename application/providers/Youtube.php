<?php

use Outlandish\SocialMonitor\Adapter\YoutubeAdapter;
use Outlandish\SocialMonitor\Engagement\EngagementScore;
use Outlandish\SocialMonitor\Models\Status;
use Outlandish\SocialMonitor\Models\YoutubeComment;
use Outlandish\SocialMonitor\Models\YoutubeVideo;
use Outlandish\SocialMonitor\PresenceType\PresenceType;

class Provider_Youtube extends Provider_Abstract
{
    protected $connection = null;

    private $videoHistoryColumns = ['views', 'likes', 'dislikes', 'comments'];
    private $commentTableName;
    public static $historyTableName = 'youtube_video_history';

    public function __construct(PDO $db, YoutubeAdapter $adapter, PresenceType $type)
    {
        parent::__construct($db, $adapter, $type, 'youtube_video_stream');
        $this->commentTableName = 'youtube_comment_stream';
        $this->engagementStatement = '(likes + number_of_replies * 4)';
    }

    public function fetchStatusData(Model_Presence $presence)
    {
        $count = 0;
        if (!$presence->getUID()) {
            throw new Exception('Presence not initialised/found');
        }

        // get all videos - we need to update all of them as they are all potentially contributing to engagement

        $videos = $this->adapter->getStatuses($presence->getUID(), null, $presence->handle);

        $this->insertVideos($presence, $videos, $count);

        $this->updateVideoHistory($presence);

        $comments = $this->adapter->getComments($presence->handle);

        $this->insertComments($presence, $comments);

        return $count;
    }

    /**
     * @param Model_Presence $presence
     * @param array $videos
     * @param mixed $count
     */
    protected function insertVideos(Model_Presence $presence, array $videos, &$count)
    {
        $insertStmt = $this->db->prepare("
			INSERT INTO `{$this->tableName}`
			(`presence_id`, `video_id`, `title`, `description`, `created_time`, `permalink`,
			`views`, `likes`, `dislikes`, `comments`)
			VALUES
			(:presence_id, :video_id, :title, :description, :created_time,
				:permalink, :views, :likes, :dislikes, :comments)
            ON DUPLICATE KEY UPDATE
                `likes` = VALUES(`likes`), `dislikes` = VALUES(`dislikes`),
                `comments` = VALUES(`comments`), `views` = VALUES(`views`)
		");

        $count = 0;

        /** @var YoutubeVideo $video */
        foreach ($videos as $video) {
            $args = array(
                ':presence_id' => $presence->getId(),
                ':video_id' => $video->id,
                ':title' => $video->title,
                ':description' => $video->description,
                ':created_time' => gmdate('Y-m-d H:i:s', $video->created_time),
                ':permalink' => $video->permalink,
                ':views' => $video->views,
                ':likes' => $video->likes,
                ':dislikes' => $video->dislikes,
                ':comments' => $video->comments,
            );
            try {
                $result = $insertStmt->execute($args);
                if (!$result) {
                    $error = $insertStmt->errorInfo();
                    error_log('Error inserting youtube video: ' . $error[2]);
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

    /**
     *
     * Store a snapshot of the YoutubeVideo data, so we can track how views, likes, dislikes and comments change over
     * time.
     *
     * @param Model_Presence $presence
     */
    private function updateVideoHistory($presence)
    {
        $query = $this->db->prepare("SELECT * FROM `youtube_video_stream` WHERE `presence_id`=" . $presence->getId());
        $query->execute();
        $result = $query->fetchAll(PDO::FETCH_ASSOC);

        $insertStmt = $this->db->prepare("
        	INSERT INTO `youtube_video_history`
        	(`video_id`, `datetime`, `type`, `value`)
        	VALUES
        	(:id, :datetime, :type, :value)
        	ON DUPLICATE KEY UPDATE
        	`value` = VALUES(`value`)
        ");
        $date = gmdate('Y-m-d H:i:s');

        foreach ($result as $row) {
            foreach ($this->videoHistoryColumns as $type) {
                $value = $row[$type];
                if (!is_null($value)) {
                    $result = $insertStmt->execute(array(
                        ':id' => $row['id'],
                        ':datetime' => $date,
                        ':type' => $type,
                        ':value' => $value
                    ));
                    if (!$result) {
                        $error = $insertStmt->errorInfo();
                        error_log('Error saving youtube video history: ' . $error[2]);
                    }
                }
            }
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
            ':id' => $presence->getId(),
            ':start' => $start->format('Y-m-d H:i:s'),
            ':end' => $end->format('Y-m-d H:i:s')
        ));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update(Model_Presence $presence)
    {
        parent::update($presence);
        $presence->youtube_engagement = $this->calculateYoutubeEngagement($presence);
    }

    public function calculateYoutubeEngagement(Model_Presence $presence)
    {
        $now = new DateTime();
        $then = clone $now;
        $then->modify("-1 week");

        $query = new Outlandish\SocialMonitor\Engagement\Query\WeightedYoutubeEngagementQuery($this->db);
        $metric = new Outlandish\SocialMonitor\Engagement\EngagementMetric($query);

        return $metric->get($presence->getId(), $now, $then);
    }

    public function updateMetadata(Model_Presence $presence)
    {

        try {
            $metadata = $this->adapter->getMetadata($presence->handle);
        } catch (Exception $e) {
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
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $r) {
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
        $postIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return $postIds;
    }

    /**
     * @param Model_Presence $presence
     * @param YoutubeComment[] $comments
     */
    private function insertComments(Model_Presence $presence, array $comments)
    {
        $insertStmt = $this->db->prepare("
			INSERT INTO `{$this->commentTableName}`
			(`id`, `presence_id`, `video_id`, `message`, `created_time`,
			`likes`, `number_of_replies`, `in_response_to`, `posted_by_owner`, `rating`, `author_channel_id`)
			VALUES
			(:id, :presence_id, :video_id, :message, :created_time, :likes,
				:number_of_replies, :in_response_to, :posted_by_owner, :rating, :author_channel_id)
            ON DUPLICATE KEY UPDATE
                `likes` = VALUES(`likes`), `rating` = VALUES(`rating`);
		");

        $count = 0;

        foreach ($comments as $comment) {
            $args = array(
                ':id' => $comment->id,
                ':presence_id' => $presence->getId(),
                ':video_id' => $comment->videoId,
                ':message' => $comment->message,
                ':created_time' => gmdate('Y-m-d H:i:s', $comment->created_time),
                ':likes' => $comment->likes,
                ':number_of_replies' => $comment->numberOfReplies,
                ':in_response_to' => $comment->in_response_to_status_uid,
                ':posted_by_owner' => $comment->posted_by_owner,
                ':rating' => $comment->rating,
                ':author_channel_id' => $comment->authorChannelId,
            );
            try {
                $result = $insertStmt->execute($args);
                if (!$result) {
                    $error = $insertStmt->errorInfo();
                    error_log('Error inserting youtube comment: ' . $error[2]);
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

    /**
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @param string $search
     * @param string $order
     * @param string $limit
     * @param string $offset
     * @return object
     */
    public function getStatusStream(Model_Presence $presence, $start, $end, $search, $order, $limit, $offset)
    {
        return $this->getStatusStreamMulti([$presence], $start, $end, $start, $order, $limit, $offset);
    }

    /**
     * @param $presences
     * @param DateTime $start
     * @param DateTime $end
     * @param $search
     * @param $order
     * @param $limit
     * @param $offset
     * @return object
     */
    public function getStatusStreamMulti($presences, $start, $end, $search, $order, $limit, $offset)
    {
        $clauses = array(
            'p.created_time >= :start',
            'p.created_time <= :end',
        );
        $args = array(
            ':start' => $start->format('Y-m-d H:i:s'),
            ':end' => $end->format('Y-m-d H:i:s'),
        );
        if ($presences && count($presences)) {
            $ids = array_map(function (Model_Presence $p) {
                return $p->getId();
            }, $presences);
            $clauses[] = 'p.presence_id IN (' . implode($ids, ',') . ')';
        }
        $searchArgs = $this->getSearchClauses($search, array('p.message'));
        $clauses = array_merge($clauses, $searchArgs['clauses']);
        $args = array_merge($args, $searchArgs['args']);

        $sql = "
			SELECT SQL_CALC_FOUND_ROWS p.*
			FROM {$this->commentTableName} AS p
			WHERE " . implode(' AND ', $clauses);
        $sql .= $this->getOrderSql($order, array('date' => 'created_time', 'engagement' => $this->engagementStatement));
        $sql .= $this->getLimitSql($limit, $offset);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($args);
        $ret = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $objects = $this->parseStatuses($ret);
        $total = $this->db->query('SELECT FOUND_ROWS()')->fetch(PDO::FETCH_COLUMN);

        return (object)array(
            'stream' => count($objects) ? $objects : null,
            'total' => $total
        );
    }

    /**
     * @param Model_Presence $presence the presence to fetch the data for
     * @param DateTime $start the date from which to fetch historic data from (inclusive)
     * @param DateTime $end the date from which to fetch historic data to (inclusive)
     * @param array $types the types of data to be returned from the history table (if empty all types will be returned)
     * @return array
     */
    public function getHistoryData(Model_Presence $presence, \DateTime $start, \DateTime $end, $types = [])
    {
        if (empty($types)) {
            $types = $this->videoHistoryColumns;
        }

        $sql = "SELECT id FROM youtube_video_stream WHERE `presence_id` = :presence_id";
        $statement = $this->db->prepare($sql);
        $result = $statement->execute([':presence_id' => $presence->getId()]);

        if (!$result) {
            return [];
        }

        $videoIds = $statement->fetchAll(PDO::FETCH_COLUMN);

        $arguments = [
            ':start_date' => $start->format("Y-m-d"),
            ':end_date' => $end->format("Y-m-d"),
        ];

        $tableName = self::$historyTableName;
        $sql = "SELECT `datetime`, `type`, SUM(`value`) AS `value` FROM {$tableName}
                WHERE `video_id` IN ('" . implode("','", $videoIds) . "')
                AND `datetime` <= :end_date
                AND `datetime` >= :start_date
                AND type IN ('" . implode("','", $types) . "')
                GROUP BY `type`, `datetime`;";

        $statement = $this->db->prepare($sql);
        $result = $statement->execute($arguments);
        if ($result) {
            $data = $statement->fetchAll(PDO::FETCH_OBJ);
        } else {
            $data = [];
        }

        return $data;
    }

    protected function parseStatuses($raw)
    {
        if (!$raw || !count($raw)) {
            return [];
        }
        $parsed = array();
        foreach ($raw as $r) {
            $status = new Status();
            $status->id = $r['id'];
            $status->message = $r['message'];
            $status->created_time = $r['created_time'];
            $presence = Model_PresenceFactory::getPresenceById($r['presence_id']);
            $status->presence_id = $r['presence_id'];
            $status->presence_name = $presence->getName();
            $status->icon = $this->type->getSign();
            $status->engagement = [
                'comments' => $r['number_of_replies'],
                'likes' => $r['likes'],
                'comparable' => (($r['likes'] + $r['number_of_replies'] * 4) / 5)
            ];
            $status->permalink = 'https://www.youtube.com/watch?v=' . $r['video_id'];
            $parsed[] = (array)$status;
        }

        return $parsed;
    }

}