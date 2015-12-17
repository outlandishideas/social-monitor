<?php


use Outlandish\SocialMonitor\Adapter\YoutubeAdapter;
use Outlandish\SocialMonitor\Models\InstagramStatus;
use Outlandish\SocialMonitor\Models\YoutubeVideo;

class Provider_Youtube extends Provider_Abstract
{
	protected $connection = null;
    /**
     * @var YoutubeAdapter
     */
    private $adapter;

    public function __construct(PDO $db, YoutubeAdapter $adapter) {
		parent::__construct($db);
		$this->type = Enum_PresenceType::YOUTUBE();
        $this->tableName = 'youtube_video_stream';
        $this->adapter = $adapter;
    }

	public function fetchStatusData(Model_Presence $presence)
	{
        $count = 0;
        if (!$presence->getUID()) {
            throw new Exception('Presence not initialised/found');
        }

        // get all videos - there aren't very many so we can update them all

        $videos = $this->adapter->getStatuses($presence->getUID(),null,$presence->handle);

        $this->insertStatuses($presence, $videos, $count);

        return $count;
    }

    /**
     * @param Model_Presence $presence
     * @param array          $videos
     * @param mixed          $count
     */
    protected function insertStatuses(Model_Presence $presence, array $videos, &$count)
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
                if(!$result) {
                    $error = $insertStmt->errorInfo();
                    error_log('Error inserting youtube video: '.$error[2]);
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

	public function getHistoricStream(Model_Presence $presence, \DateTime $start, \DateTime $end,
        $search = null, $order = null, $limit = null, $offset = null)
	{
        $clauses = array(
            'p.created_time >= :start',
            'p.created_time <= :end',
            'p.presence_id = :id'
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

		return (object)array(
            'stream' => count($ret) ? $ret : null,
            'total' => $total
        );
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
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function update(Model_Presence $presence)
	{
		parent::update($presence);
        $presence->youtube_engagement = $this->calculateYoutubeEngagement($presence);
    }

    public function calculateYoutubeEngagement(Model_Presence $presence)
    {
        return 0;
    }

    public function updateMetadata(Model_Presence $presence) {

        try {
            $metadata = $this->adapter->getMetadata($presence->handle);
        } catch (Exception_FacebookNotFound $e) {
            $presence->uid = null;
            throw $e;
        }

        $presence->type = $this->type;
        $presence->uid = $metadata->uid;
        $presence->name = $metadata->name;
        $presence->page_url = $metadata->page_url;
        $presence->popularity = $metadata->popularity;
        $presence->image_url = $metadata->image_url;
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


}