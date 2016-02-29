<?php

use Outlandish\SocialMonitor\Adapter\TwitterAdapter;
use Outlandish\SocialMonitor\Models\Status;
use Outlandish\SocialMonitor\Engagement\EngagementScore;
use Outlandish\SocialMonitor\Models\Tweet;

class Provider_Twitter extends Provider_Abstract
{
    protected $connection = null;

    protected $kloutApi = null;
    protected $adapter;

    const KLOUT_API_ENDPOINT = 'http://api.klout.com/v2/';

    public function __construct(PDO $db, TwitterAdapter $adapter)
    {
        parent::__construct($db);
        $this->type = Enum_PresenceType::TWITTER();
        $this->tableName = 'twitter_tweets';
        $this->adapter = $adapter;
        $this->engagementStatement = '(retweet_count)';
        $this->contentColumn = 'text_expanded';
    }

    public function fetchStatusData(Model_Presence $presence)
    {
        if (!$presence->uid) {
            throw new Exception('Presence not initialised/found');
        }
//        $fetchCount->type = 'tweet';
        $stmt = $this->db->prepare("SELECT tweet_id FROM {$this->tableName} WHERE presence_id = :id ORDER BY created_time DESC LIMIT 1");
        $stmt->execute(array(':id' => $presence->getId()));
        $lastTweetId = $stmt->fetchColumn();

        $tweetsAndMentions = $this->adapter->getStatuses($presence->getUID(), $lastTweetId, $presence->getHandle());


        $count = 0;
        $count += $this->insertTweets($presence, $tweetsAndMentions);
        return $count;
    }

    /**
     * @param Model_Presence $presence
     * @param array $tweetData
     * @return array
     */
    protected function insertTweets($presence, $tweetData)
    {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->tableName}
            (tweet_id, presence_id, text_expanded, created_time, retweet_count, html_tweet, permalink,
                responsible_presence, needs_response, in_reply_to_user_uid, in_reply_to_status_uid)
            VALUES
            (:tweet_id, :presence_id, :text_expanded, :created_time, :retweet_count, :html_tweet, :permalink,
                :responsible_presence, :needs_response, :in_reply_to_user_uid, :in_reply_to_status_uid)
            ON DUPLICATE KEY UPDATE
            retweet_count = VALUES(retweet_count)");

        $presenceId = $presence->getId();
        $count = 0;
        $links = array();
        while ($tweetData) {
            /** @var Tweet $tweet */
            $tweet = array_shift($tweetData);
            $permalink = Model_TwitterTweet::getTwitterUrl($presence->handle, $tweet->id);
            $args = array(
                ':tweet_id' => $tweet->id,
                ':presence_id' => $presenceId,
                ':text_expanded' => $tweet->message,
                ':created_time' => $tweet->created_time,
                ':retweet_count' => $tweet->share_count,
                ':html_tweet' => $tweet->html,
                ':permalink' => $permalink,
                ':responsible_presence' => $tweet->posted_by_owner ? null : $presenceId,
                ':needs_response' => $tweet->needs_response,
                ':in_reply_to_user_uid' => $tweet->in_response_to_user_uid,
                ':in_reply_to_status_uid' => $tweet->in_response_to_status_uid
            );
            try {
                $result = $stmt->execute($args);
                if (!$result) {
                    error_log('error inserting tweet:' . implode(',', $stmt->errorInfo()));
                }
                $id = $this->db->lastInsertId();
                if (!empty($tweet->links)) {
                    $links[$id] = $tweet->links;
                }
                $count++;
            } catch (Exception $ex) {
                $i = 0;
            }
        }

        $this->saveLinks('twitter', $links);

        return $count;
    }

    protected function decorateStreamData(&$statuses)
    {
        // decorate tweets with links
        $tweetIds = array();
        foreach ($statuses as $tweet) {
            $tweetIds[] = $tweet['id'];
        }

        $links = $this->getLinks($tweetIds, 'twitter');
        foreach ($statuses as &$r) {
            $id = $r['id'];
            $r['links'] = isset($links[$id]) ? $links[$id] : array();
        }
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
                        " . ($ownPostsOnly ? 'AND responsible_presence IS NULL' : '') . "
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
                        " . ($ownPostsOnly ? 'AND p.responsible_presence IS NULL' : '') . "
					GROUP BY
						DATE_FORMAT(p.created_time, '%Y-%m-%d')
				) AS links ON (posts.date = links.date)
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
        if (!$this->kloutApi) {
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
        if ($apiKey) {
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

    public function updateMetadata(Model_Presence $presence)
    {

        try {
            $data = $this->adapter->getMetadata($presence->handle);
        } catch (Exception_TwitterNotFound $e) {
            $presence->uid = null;
            throw $e;
        }

        $presence->type = $this->type;
        $presence->uid = $data->uid;
        $presence->image_url = $data->image_url;
        $presence->name = $data->name;
        $presence->page_url = $data->page_url;
        $presence->popularity = $data->popularity;
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
        $clauses = array(
            't.responsible_presence = :pid',
            't.needs_response = 1',
            't.created_time >= :start_date',
            't.created_time <= :end_date'
        );
        $args = array(
            ':pid' => $presence->getId(),
            ':start_date' => $start->format('Y-m-d'),
            ':end_date' => $end->format('Y-m-d')
        );
        $stmt = $this->db->prepare("
          SELECT t.tweet_id as id, t.created_time as created, TIME_TO_SEC( TIMEDIFF( r.created_time, t.created_time ))/3600 AS time
          FROM {$this->tableName} AS t
            INNER JOIN {$this->tableName} AS r ON t.tweet_id = r.in_reply_to_status_uid
            WHERE " . implode(' AND ', $clauses) . "");
        $stmt->execute($args);
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $r) {
            $key = $r->id;
            if (!array_key_exists($key, $responseData)) {
                $responseData[$key] = (object)array('diff' => null, 'created' => null);
            }
            if (empty($responseData[$key]->diff) || $r->time < $responseData[$key]->diff) {
                $responseData[$key]->diff = $r->time;
                $responseData[$key]->created = $r->created;
            }
        }
        return $responseData;
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
            $status->message = $r['html_tweet'];
            $status->created_time = $r['created_time'];
            $status->permalink = $r['permalink'];
            $presence = Model_PresenceFactory::getPresenceById($r['presence_id']);
            $status->presence_id = $r['presence_id'];
            $status->presence_name = $presence->getName();
            $status->engagement = [
                'shares' => $r['retweet_count'],
                'comparable' => $r['retweet_count']
            ];
            $status->icon = Enum_PresenceType::TWITTER()->getSign();
            $parsed[] = (array)$status;
        }
        return $parsed;
    }

    /**
     * @param Model_Presence $presence
     * @return EngagementScore
     */
    function getEngagementScore($presence)
    {
        return new EngagementScore('Klout score', 'klout', $presence->getKloutScore());
    }
}