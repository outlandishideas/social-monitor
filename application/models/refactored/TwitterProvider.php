<?php


class NewModel_TwitterProvider extends NewModel_iProvider
{
	protected $connection = null;

	protected $tableName = 'twitter_tweets';
	protected $type = null;
	protected $kloutApi = null;

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

	public function update($presence)
	{
		$data = parent::updateNew($presence->getHandle());
		if($data){
			$kloutId = $presence->getKloutId();
			if($kloutId){
				$data['klout_id'] = $kloutId;
				$data['klout_score'] = $this->getKloutScore($kloutId);
			}
		}
		return $data;
	}

	public function updateNew($handle)
	{
		$data = parent::updateNew($handle);
		$kloutId = $this->getKloutId($data['uid']);
		if($kloutId){
			$data['klout_id'] = $kloutId;
			$data['klout_score'] = $this->getKloutScore($kloutId);
		}
		return $data;
	}

	protected function getKloutApi()
	{
		if(!$this->kloutApi){
			try {
				$this->kloutApi = Zend_Registry::get('config')->klout->api_key;
			} catch (Exception $e) {
				$this->kloutApi = null;
			}
		}
		return $this->kloutApi;
	}

	/**
	 * @param $uid
	 * @return mixed|null
	 */
	public function getKloutId($uid)
	{
		$apiKey = $this->getKloutApi();
		if($apiKey){
			$json = Util_Http::fetchJson(self::KLOUT_API_ENDPOINT . 'identity.json/tw/' . $this->uid . '?key=' . $apiKey);
			return $json->id;
		}
		return null;
	}

	/**
	 * @param $kloutId
	 * @return mixed|null
	 */
	public function getKloutScore($kloutId)
	{
		$apiKey = $this->getKloutApi();
		if($apiKey){
			try {
				$json = Util_Http::fetchJson(self::KLOUT_API_ENDPOINT . 'user.json/' . $this->klout_id . '?key=' . $apiKey);
				return $json->score->score;
			} catch (RuntimeException $ex) {
				if ($ex->getCode() == 404) {
					/* Do Something */
				}
			}
		}
		return null;
	}

	public function handleData($handle) {#

		try {
			$data = Util_Twitter::userInfo($handle);
		} catch (Exception_TwitterNotFound $e) {
			return null;
//			throw new Exception_TwitterNotFound('Twitter user not found: ' . $this->handle, $e->getCode(), $e->getPath(), $e->getErrors());
		}

		//test if user exists
		return array(
			"type" => NewModel_PresenceType::TWITTER, //type
			"handle" => $handle, //handle
			"uid" => $data->id_str, //uid
			"image_url" => $data->profile_image_url, //image_url
			"name" => $data->name, //name
			"page_url" => 'http://www.twitter.com/' . $data->screen_name, //page_url
			"followers" => $data->followers_count,  //popularity
			"klout_id" => null,  //klout_id
			"klout_score" => null,  //klout_score
			"facebook_engagement" => null,  //facebook_engagement
			"last_updated" => gmdate('Y-m-d H:i:s') //last_updated
		);
	}
}