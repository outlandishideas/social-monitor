<?php


class NewModel_TwitterProvider extends NewModel_iProvider
{
	protected $connection = null;

	protected $tableName = 'twitter_tweets';
	protected $type = null;

	const KLOUT_API_ENDPOINT = 'http://api.klout.com/v2/';

	public function __construct(PDO $db) {
		parent::__construct($db);
		$this->type = NewModel_PresenceType::TWITTER();
	}

	public function fetchData(NewModel_Presence $presence)
	{
		if (!$presence->uid) {
			throw new Exception('Presence not initialised/found');
		}



		return array();
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
						AND type = 'twitter'
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
						LEFT JOIN status_links AS sl ON (p.id = sl.status_id AND sl.type = 'twitter')
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
		return 0;
	}

	public function testHandle($handle) {#

		try {
			$data = Util_Twitter::userInfo($handle);
		} catch (Exception_TwitterNotFound $e) {
			return false;
//			throw new Exception_TwitterNotFound('Twitter user not found: ' . $this->handle, $e->getCode(), $e->getPath(), $e->getErrors());
		}

		// update the klout score (not currently possible for facebook pages)
		//todo: get klout score
//		try {
//			$apiKey = Zend_Registry::get('config')->klout->api_key;
//			$success = $this->updateKloutScore($apiKey);
//			if (!$success) {
//				$this->klout_id = null;
//				$this->updateKloutScore($apiKey);
//			}
//		} catch (Exception $ex) { /* ignore */ }

//		if (!$this->klout_id) {
//			$json = Util_Http::fetchJson(self::KLOUT_API_ENDPOINT . 'identity.json/tw/' . $this->uid . '?key=' . $apiKey);
//			$this->klout_id = $json->id;
//		}
//		if ($this->klout_id) {
//			try {
//				$json = Util_Http::fetchJson(self::KLOUT_API_ENDPOINT . 'user.json/' . $this->klout_id . '?key=' . $apiKey);
//				$this->klout_score = $json->score->score;
//			} catch (RuntimeException $ex) {
//				if ($ex->getCode() == 404) {
//					/* Do Something */
//				}
//			}
//		}

		//test if user exists
		return array(
			NewModel_PresenceType::TWITTER, //type
			$handle, //handle
			$data->id_str, //uid
			$data->profile_image_url, //image_url
			$data->name, //name
			'http://www.twitter.com/' . $data->screen_name, //page_url
			$data->followers_count, //popularity
			gmdate('Y-m-d H:i:s') //last_updated
		);
	}
}