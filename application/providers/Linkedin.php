<?php

use Outlandish\SocialMonitor\Adapter\LinkedinAdapter;
use Outlandish\SocialMonitor\Adapter\YoutubeAdapter;
use Outlandish\SocialMonitor\Models\InstagramStatus;
use Outlandish\SocialMonitor\Models\LinkedinStatus;
use Outlandish\SocialMonitor\Models\YoutubeComment;
use Outlandish\SocialMonitor\Models\YoutubeVideo;

class Provider_Linkedin extends Provider_Abstract
{
	protected $connection = null;
    /**
     * @var YoutubeAdapter
     */
    private $adapter;

    public function __construct(PDO $db, LinkedinAdapter $adapter) {
		parent::__construct($db);
		$this->type = Enum_PresenceType::LINKEDIN();
        $this->tableName = 'linkedin_stream';
        $this->adapter = $adapter;
    }

	public function fetchStatusData(Model_Presence $presence)
	{
        $count = 0;
        if (!$presence->getUID()) {
            throw new Exception('Presence not initialised/found');
        }

        // get all videos - we need to update all of them as they are all potentially contributing to engagement

        $statuses = $this->adapter->getStatusesWithAccessToken($presence->getUID(), null, $presence->handle, 'token');

        $this->insertStatuses($presence, $statuses, $count);

        return $count;
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
            'stream' => count($ret) ? $ret : [],
            'total' => $total
        );
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
						LEFT JOIN status_links AS sl ON (p.id = sl.status_id AND sl.type = 'linkedin')
						LEFT JOIN domains AS d ON (sl.domain = d.domain)
					WHERE
						p.created_time >= :start
						AND p.created_time <= :end
						AND p.presence_id = :id
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
        $presence->instagram_engagement = $this->calculateEngagement($presence);
    }

    public function updateMetadata(Model_Presence $presence) {

        try {
            $metadata = $this->adapter->getMetadataWithAccessToken($presence->handle, 'token');
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
     * @param LinkedinStatus[] $statuses
     * @param $count
     * @internal param \Outlandish\SocialMonitor\Models\YoutubeComment[] $comments
     */
    private function insertStatuses(Model_Presence $presence, array $statuses, &$count)
    {
        $insertStmt = $this->db->prepare("
			INSERT INTO `{$this->tableName}`
			(`post_id`, `presence_id`, `message`, `created_time`,
			`likes`, `comments`, `type`)
			VALUES
			(:post_id, :presence_id, :message, :created_time, :likes,
				:comments, :type)
            ON DUPLICATE KEY UPDATE
                `likes` = VALUES(`likes`), `comments` = VALUES(`comments`);
		");

        $count = 0;
        $links = [];

        foreach ($statuses as $status) {
            $args = array(
                ':post_id' => $status->postId,
                ':presence_id' => $presence->getId(),
                ':message' => substr($status->message, 0, 2000),
                ':created_time' => gmdate('Y-m-d H:i:s', $status->created_time),
                ':likes' => $status->likes,
                ':comments' => $status->comments,
                ':type' => $status->type
            );
            try {
                $result = $insertStmt->execute($args);
                if(!$result) {
                    $error = $insertStmt->errorInfo();
                    error_log('Error inserting youtube comment: '.$error[2]);
                    continue;
                }
                $id = $this->db->lastInsertId();
                //only inset status links if we have one, and the lastInsertId is not 0
                //lastInsertId will be 0 if we have just saved a status that has already been saved
                if (!empty($status->links) && $id != 0) {
                    $links[$id] = $status->links;
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

        $this->saveLinks('linkedin', $links);

    }

    /**
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return array
     */
    public function getResponseData(Model_Presence $presence, DateTime $start, DateTime $end)
    {
        // TODO: Implement getResponseData() method.
    }

    private function calculateEngagement($presence)
    {
        $now = new DateTime();
        $then = clone $now;
        $then->modify("-1 week");

        $query = new Outlandish\SocialMonitor\Engagement\Query\WeightedLinkedinEngagementQuery($this->db);
        $metric = new Outlandish\SocialMonitor\Engagement\EngagementMetric($query);

        return $metric->get($presence->getId(), $now, $then);
    }
}