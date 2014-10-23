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

	public function fetchStatusData(NewModel_Presence $presence)
	{
		if (!$presence->getUID()) {
			throw new Exception('Presence not initialised/found');
		}


		$stmt = $this->db->prepare("SELECT created_time FROM {$this->tableName} WHERE presence_id = :id ORDER BY created_time DESC LIMIT 1");
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

	public function update(NewModel_Presence $presence)
	{
		parent::update($presence);
        $presence->facebook_engagement = $this->calculateFacebookEngagement($presence);
	}

	protected function calculateFacebookEngagement(NewModel_Presence $presence)
	{
		$week = new DateInterval('P6D');
		$end = new DateTime();
		$start = clone $end;
		$start = $start->sub($week);

		$score = null;

		$results = $this->getFacebookCommentsSharesLikes($presence, $start, $end);

		if(!empty($results)){

			$total = array_reduce($results, function($total, $status){
				$total += $status['comments'];
				$total += $status['likes'];
				$total += $status['share_count'];
				return $total;
			}, 0);

			if($total > 0) {
				$last = end($s);
				if($last['popularity'] < 0){
					$score = ($total / $last['popularity']) * 1000;
				}
			}
		}
		return $score;
	}

	protected function getFacebookCommentsSharesLikes(NewModel_Presence $presence, DateTime $start, DateTime $end)
	{
		$args = array(
			':pid' => $presence->getId(),
			':start_time' => $start->format("Y-m-d"),
			':end_time' => $end->format("Y-m-d")
		);

		$sql = "
            SELECT ph.created_time as time, SUM(fs.comments) as comments, SUM(fs.likes) as likes, SUM(fs.share_count) as share_count, ph.popularity
            FROM (
                SELECT presence_id, DATE(datetime) as created_time, MAX(value) as popularity
                FROM presence_history
                WHERE type = 'popularity'
                AND presence_id = :pid
                AND DATE(datetime) >= :start_time
                AND DATE(datetime) <= :end_time
                GROUP BY DATE(datetime) ) as ph
            LEFT JOIN facebook_stream as fs
            ON DATE(fs.created_time) = ph.created_time
            AND fs.presence_id = ph.presence_id
            WHERE ph.created_time >= :start_time
            AND ph.created_time <= :end_time
            GROUP BY ph.created_time";

		$stmt = $this->db->prepare($sql);
		$stmt->execute($args);
		return $stmt->fetchAll(PDO::FETCH_OBJ);
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

	public function updateMetadata(NewModel_Presence $presence) {

        $data = Util_Facebook::pageInfo($presence->handle);

        $presence->type = NewModel_PresenceType::FACEBOOK();
        $presence->uid = $data['page_id'];
        $presence->image_url = $data['pic_square'];
        $presence->name = $data['name'];
        $presence->page_url = $data['page_url'];
        $presence->popularity = $data['fan_count'];
	}
}