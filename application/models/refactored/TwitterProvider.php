<?php


class NewModel_TwitterProvider extends NewModel_iProvider
{
	protected $connection = null;

	protected $kloutApi = null;

	const KLOUT_API_ENDPOINT = 'http://api.klout.com/v2/';

	public function __construct(PDO $db) {
		parent::__construct($db);
		$this->type = NewModel_PresenceType::TWITTER();
        $this->tableName = 'twitter_tweets';
	}

	public function fetchStatusData(NewModel_Presence $presence)
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

	public function update(NewModel_Presence $presence)
	{
        parent::update($presence);
        $kloutId = $presence->getKloutId();
        if(!$kloutId){
            $kloutId = $this->getKloutId($presence->getUID());
            $presence->klout_id = $kloutId;
        }
        $presence->klout_score = $this->getKloutScore($kloutId);
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
			$json = Util_Http::fetchJson(self::KLOUT_API_ENDPOINT . 'identity.json/tw/' . $uid . '?key=' . $apiKey);
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
				$json = Util_Http::fetchJson(self::KLOUT_API_ENDPOINT . 'user.json/' . $kloutId . '?key=' . $apiKey);
				return $json->score->score;
			} catch (RuntimeException $ex) {
				if ($ex->getCode() == 404) {
					/* Do Something */
				}
			}
		}
		return null;
	}

	public function updateMetadata(NewModel_Presence $presence) {

        $data = Util_Twitter::userInfo($presence->handle);

        $presence->type = $this->type;
        $presence->uid = $data->id_str;
        $presence->image_url = $data->profile_image_url;
        $presence->name = $data->name;
        $presence->page_url = 'http://www.twitter.com/' . $data->screen_name;
        $presence->popularity = $data->followers_count;
	}
}