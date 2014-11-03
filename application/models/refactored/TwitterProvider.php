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
//        $fetchCount->type = 'tweet';
        $stmt = $this->db->prepare("SELECT tweet_id FROM {$this->tableName} WHERE presence_id = :id ORDER BY created_time DESC LIMIT 1");
        $stmt->execute(array(':id'=>$presence->getId()));
        $lastTweetId = $stmt->fetchColumn();
        $tweets = Util_Twitter::userTweets($presence->getUID(), $lastTweetId);
        $mentions = Util_Twitter::userMentions($presence->getHandle(), $lastTweetId);
        $count = 0;
        $count += $this->parseAndInsertTweets($presence, $tweets);
        $count += $this->parseAndInsertTweets($presence, $mentions, true);
        return $count;
	}

    /**
     * @param NewModel_Presence $presence
     * @param array $tweetData
     * @param bool $mentions
     * @return array
     */
    protected function parseAndInsertTweets($presence, $tweetData, $mentions = false) {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->tableName}
            (tweet_id, presence_id, text_expanded, created_time, retweet_count, html_tweet,
                responsible_presence, needs_response, in_reply_to_user_uid, in_reply_to_status_uid)
            VALUES
            (:tweet_id, :presence_id, :text_expanded, :created_time, :retweet_count, :html_tweet,
                :responsible_presence, :needs_response, :in_reply_to_user_uid, :in_reply_to_status_uid)
            ON DUPLICATE KEY UPDATE
            retweet_count = VALUES(retweet_count)");

        $presenceId = $presence->getId();
        $presenceUID = $presence->getUID();
        $count = 0;
        $links = array();
        while ($tweetData) {
            $tweet = array_shift($tweetData);
            $parsedTweet = Util_Twitter::parseTweet($tweet);
            $isRetweet = isset($tweet->retweeted_status) && $tweet->retweeted_status->user->id == $presenceUID;
            $args = array(
                ':tweet_id' => $tweet->id_str,
                ':presence_id' => $presenceId,
                ':text_expanded' => $parsedTweet['text_expanded'],
                ':created_time' => gmdate('Y-m-d H:i:s', strtotime($tweet->created_at)),
                ':retweet_count' => $tweet->retweet_count,
                ':html_tweet' => $parsedTweet['html_tweet'],
                ':responsible_presence' => $mentions ? $presenceId : null,
                ':needs_response' => $mentions && !$isRetweet ? 1 : 0,
                ':in_reply_to_user_uid' => $tweet->in_reply_to_user_id_str,
                ':in_reply_to_status_uid' => $tweet->in_reply_to_status_id_str
            );
            try {
                $stmt->execute($args);
                $id = $this->db->lastInsertId();
                if (!empty($tweet->entities->urls)) {
                    $links[$id] = array_map(function($a) { return $a->expanded_url; }, $tweet->entities->urls);
                }
                $count++;
            } catch (Exception $ex) {
                $i=0;
            }
        }

        $this->saveLinks('twitter', $links);

        return $count;
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


	public function update(NewModel_Presence $presence)
	{
        parent::update($presence);
        $kloutId = $presence->getKloutId();
        if (!$kloutId) {
            $kloutId = $this->lookupKloutId($presence->getUID());
            $presence->klout_id = $kloutId;
        }
        if ($kloutId) {
            $presence->klout_score = $this->lookupKloutScore($kloutId);
        } else {
            $presence->klout_score = null;
        }
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
	public function lookupKloutId($uid)
	{
		$apiKey = $this->getKloutApi();
		if ($apiKey) {
            try {
    			$json = Util_Http::fetchJson(self::KLOUT_API_ENDPOINT . 'identity.json/tw/' . $uid . '?key=' . $apiKey);
            } catch (Exception $ex) {
                return null;
            }
			return $json->id;
		}
		return null;
	}

	/**
	 * @param $kloutId
	 * @return mixed|null
	 */
	public function lookupKloutScore($kloutId)
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

        try {
            $data = Util_Twitter::userInfo($presence->handle);
        } catch (Exception_TwitterNotFound $e) {
            $presence->uid = null;
            throw new Exception_TwitterNotFound('Twitter user not found: ' . $presence->handle, $e->getCode(), $e->getPath(), $e->getErrors());
        }

        $presence->type = $this->type;
        $presence->uid = $data->id_str;
        $presence->image_url = $data->profile_image_url;
        $presence->name = $data->name;
        $presence->page_url = 'https://www.twitter.com/' . $data->screen_name;
        $presence->popularity = $data->followers_count;
	}

    /**
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return array
     */
    public function getResponseData(NewModel_Presence $presence, DateTime $start, DateTime $end)
    {
        $responseData = array();
        $clauses = array(
            't.responsible_presence = :pid',
            't.needs_response = 1',
            't.created_time >= :start_date',
            't.created_time <= :end_date'
        );
        $args = array(
            ':pid'=>$presence->getId(),
            ':start_date' => $start->format('Y-m-d'),
            ':end_date' => $end->format('Y-m-d')
        );
        $stmt = $this->db->prepare("
          SELECT t.tweet_id as id, t.created_time as created, TIME_TO_SEC( TIMEDIFF( r.created_time, t.created_time ))/3600 AS time
          FROM {$this->tableName} AS t
            INNER JOIN {$this->tableName} AS r ON t.tweet_id = r.in_reply_to_status_uid
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
        }
        return $responseData;
    }


}