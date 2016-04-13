<?php

use Outlandish\SocialMonitor\Adapter\LinkedinAdapter;
use Outlandish\SocialMonitor\Database\Database;
use Outlandish\SocialMonitor\Engagement\EngagementScore;
use Outlandish\SocialMonitor\Exception\SocialMonitorException;
use Outlandish\SocialMonitor\Models\LinkedinStatus;
use Outlandish\SocialMonitor\Models\Status;
use Outlandish\SocialMonitor\PresenceType\PresenceType;

class Provider_Linkedin extends Provider_Abstract
{
	protected $connection = null;

    public function __construct(Database $db, LinkedinAdapter $adapter, PresenceType $type) {
		parent::__construct($db, $adapter, $type, 'linkedin_stream');
    }

	public function fetchStatusData(Model_Presence $presence)
	{
        $count = 0;
        if (!$presence->getUID()) {
            throw new Exception('Presence not initialised/found');
        }

        // get all videos - we need to update all of them as they are all potentially contributing to engagement

        $statuses = $this->adapter->getStatusesWithAccessToken($presence->getUID(), null, $presence->handle, $presence->getAccessToken());

        $this->insertStatuses($presence, $statuses, $count);

        return $count;
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
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function update(Model_Presence $presence)
	{
		parent::update($presence);
        $presence->linkedin_engagement = $this->calculateEngagement($presence);
    }

    public function updateMetadata(Model_Presence $presence) {

        try {
            $metadata = $this->adapter->getMetadataWithAccessToken($presence->handle, $presence->getAccessToken());
        } catch (Exception $e) {
            $presence->uid = null;
            throw $e;
        }

        $presence->type = $this->type;
		$presence->updateFromMetadata($metadata);
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
			`likes`, `comments`, `type`, `permalink`)
			VALUES
			(:post_id, :presence_id, :message, :created_time, :likes,
				:comments, :type, :permalink)
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
                ':type' => $status->type,
                ':permalink' => $status->permalink
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

    /**
     * @param Model_Presence $presence
     * @return float|null
     */
    private function calculateEngagement($presence)
    {
        $now = new DateTime();
        $then = clone $now;
        $then->modify("-1 week");

        $query = new Outlandish\SocialMonitor\Engagement\Query\WeightedLinkedinEngagementQuery($this->db);
        $metric = new Outlandish\SocialMonitor\Engagement\EngagementMetric($query);

        return $metric->get($presence->getId(), $now, $then);
    }

    /**
     * Run a simple test on the adapter to see if we can fetch the presence
     *
     * @param Model_Presence $presence
     * @throws SocialMonitorException
     * @return null
     */
    public function testAdapter(Model_Presence $presence)
    {
        $this->adapter->getChannelWithAccessToken($presence->getHandle(), $presence->getAccessToken());
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
            $status->message = $r['message'];
            $status->created_time = $r['created_time'];
            $presence = Model_PresenceFactory::getPresenceById($r['presence_id']);
            $status->presence_id = $r['presence_id'];
            $status->presence_name = $presence->getName();
            $status->permalink = $r['permalink'];
            $status->presence_type = 'linkedin';
            $status->presence_handle = $presence->getHandle();
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