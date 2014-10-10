<?php


class NewModel_FacebookProvider extends NewModel_iProvider
{
	protected $connection = null;

	protected $tableName = 'facebook_stream';
	protected $type = null;

	public function __construct(PDO $db) {
		parent::__construct($db);
		$this->type = NewModel_PresenceType::FACEBOOK();
	}

	public function fetchData(NewModel_Presence $presence)
	{
		if (!$presence->getUID()) {
			throw new Exception('Presence not initialised/found');
		}


		$stmt = $this->_db->prepare("SELECT created_time FROM {$this->tableName} WHERE presence_id = :id ORDER BY created_time DESC LIMIT 1");
		$stmt->execute(array(':id' => $presence->getId()));
		$since = $stmt->fetchColumn();
		if ($since) {
			$since = strtotime($since);
		}

		$posts = Util_Facebook::pagePosts($presence->getUID(), $since);
		foreach ($posts as $s) {
			$this->parseStatus($presence, $s);
		}

		return array();
	}

	protected function parseStatus(NewModel_Presence $presence, $post)
	{
		$id = $this->saveStatus($presence, $post);
		$post->local_id = $id;
		$postedByOwner = $post->actor_id == $presence->getUID();
		if ($postedByOwner) {
			$this->findAndSaveLinks($post);
		}
	}


	protected function saveStatus(NewModel_Presence $presence, $post)
	{
		$postedByOwner = $post->actor_id == $presence->getUID();
		$stmt = $this->db->prepare("
			INSERT INTO `{$this->tableName}`
			(`post_id`, `presence_id`, `message`, `create_time`, `actor_id`, `comments`,
				`likes`, `share_count`, `permalink`, `type`, `posted_by_owner`, `needs_response`, `in_response_to`)
			VALUES
			(:post_id, :presence_id, :message, :create_time, :actor_id, :comments,
				:likes, :share_count, :permalink, :type, :posted_by_owner, :needs_response, :in_response_to)
		");
		$args = array(
			'post_id' => $post->post_id,
			'presence_id' => $presence->id,
			'message' => $post->message,
			'created_time' => gmdate('Y-m-d H:i:s', $post->created_time),
			'actor_id' => $post->actor_id,
			'permalink' => $post->permalink,
			'comments' => isset($post->comments['count']) ? intval($post->comments['count']) : 0,
			'likes' => isset($post->likes['count']) ? intval($post->likes['count']) : 0,
			'share_count' => $post->share_count,
			'type' => $post->type,
			'posted_by_owner' => $postedByOwner,
			'needs_response' => !$postedByOwner && $post->message
		);
		$stmt->execute($args);
		return $this->db->lastInsertId();
	}

	public function getHistoricStream(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
	{
		$ret = array();
		$stmt = $this->db->prepare("
			SELECT
				p.*,
				l.links
			FROM
				{$this->tableName} AS p
				LEFT JOIN (
					SELECT
						status_id,
						GROUP_CONCAT(url) AS links
					FROM
						status_links
					WHERE
						status_id IN (
							SELECT
								`id`
							FROM
								{$this->tableName}
							WHERE
								`created_time` >= :start
								AND `created_time` <= :end
								AND `presence_id` = :id
						)
						AND type = 'facebook'
					GROUP BY
						status_id
				) AS l ON (p.id = l.status_id)
			WHERE
				p.`created_time` >= :start
				AND p.`created_time` <= :end
				AND p.`presence_id` = :id
		");
		$stmt->execute(array(
			':start'	=> $start->format('Y-m-d H:i:s'),
			':end'	=> $end->format('Y-m-d H:i:s'),
			':id'		=> $presence->getId()
		));
		$ret = $stmt->fetchAll(PDO::FETCH_ASSOC);

		//add retweets and links to posts
		foreach ($ret as &$r) {
			$r['links'] = is_null($r['links']) ? array() : explode(',', $r['links']);
		}
		return count($ret) ? $ret : null;
	}


	public function getHistoricStreamMeta(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
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
						LEFT JOIN status_links AS sl ON (p.id = sl.status_id AND sl.type = 'facebook')
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


	protected function findAndSaveLinks($streamdatum)
	{
		$newLinks = $this->extractLinks($streamdatum->message);
		foreach ($newLinks as $link) {
			$link['external_id'] = $streamdatum->post_id;
//			$link['type'] = $presence->getType();
			$links[] = $link;
		}
	}

	public function testHandle($handle) {

		try {
			$data = Util_Facebook::pageInfo($handle);
		} catch (Exception_FacebookNotFound $e) {
			return false;
//			throw new Exception_FacebookNotFound('Facebook page not found: ' . $this->handle, $e->getCode(), $e->getFql(), $e->getErrors());
		}

		$this->facebook_engagement = $this->calculateFacebookEngagement();

		//test if user exists
		//todo: add facebook engagement to this
		return array(
			NewModel_PresenceType::FACEBOOK, //type
			$handle, //handle
			$data['page_id'], //uid
			$data['pic_square'], //image_url
			$data['name'], //name
			$data['page_url'], //page_url
			$data['fan_count'], //popularity
			gmdate('Y-m-d H:i:s') //last_updated
		);
	}
}