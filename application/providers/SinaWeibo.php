<?php
use Outlandish\SocialMonitor\Models\Status;
use Outlandish\SocialMonitor\Engagement\EngagementScore;

require_once(__DIR__ . '/../../lib/sina_weibo/sinaweibo.php');

class Provider_SinaWeibo extends Provider_Abstract
{
	const BASEURL = 'http://www.weibo.com/';

	protected $connection = null;

	public function __construct(PDO $db) {
		parent::__construct($db);
		$this->connection = new SaeTClientV2('1247980630', 'cfcd7c7170b70420d7e1c00628d639c2', '2.00cBChiFql593B4e223582cb04rzB6');
		if (!array_key_exists('REMOTE_ADDR', $_SERVER)) {
			$this->connection->set_remote_ip('127.0.0.1');
		}
		$this->type = Enum_PresenceType::SINA_WEIBO();
        $this->tableName = 'sina_weibo_posts';
		$this->createdTimeColumn = 'created_at';
		$this->engagementStatement = '(attitude_count + comment_count * 4 + repost_count * 7)';
		$this->contentColumn = 'text';
	}

	public function fetchStatusData(Model_Presence $presence)
	{
		$stmt = $this->db->prepare("SELECT MAX(`remote_id`) AS `since_id` FROM `{$this->tableName}` WHERE `presence_id` = ".$presence->getId());
		$stmt->execute();
		$data = $stmt->fetch(PDO::FETCH_ASSOC);
		$since_id = $data['since_id'];
		$popularity = null;
		$page = 0;
        $count = 0;
		do {
			$page++;
			$data = $this->connection->friends_timeline($page, 200, $since_id);
            $statuses = isset($data['statuses']) ? $data['statuses'] : array();
			foreach ($statuses as $s) {
                if (!$s['user'] || $s['user']['idstr'] != $presence->getUID()) {
                    continue;
                }
				$s['presence_id'] = $presence->getId();
				$s['posted_by_presence'] = 1;
				$count += $this->parseStatus($s);
                if (!$popularity) {
                    $popularity = $s['user']['followers_count'];
                }
			}
		} while (count($statuses));
        if ($popularity) {
            $presence->popularity = $popularity;
        }
        return $count;
	}

	protected function decorateStreamData(&$posts) {
		//add retweets and links to posts
		$postIds = array();
		$remoteIds = array();
		foreach ($posts as $post) {
			$postIds[] = $post['id'];
			$remoteIds[] = $post['remote_id'];
		}

		$retweets = $this->getRetweets($remoteIds);
		$links = $this->getLinks($postIds, 'sina_weibo');

		foreach ($posts as &$r) {
			$id = $r['id'];
			$r['links'] = isset($links[$id]) ? $links[$id] : array();

			$retweet = $r['included_retweet'];
			if (!$retweet && array_key_exists($retweet, $retweets)) {
				$r['included_retweet'] = $retweets[$retweet];
			}
		}
	}

    protected function getRetweets($postIds) {
        $retweets = array();
        if ($postIds) {
            $stmt = $this->db->prepare("
                SELECT * FROM {$this->tableName} WHERE `remote_id` IN (
                    ".implode(',', array_fill(0, count($postIds), '?'))."
                )
            ");
            $stmt->execute($postIds);
            $r = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($r as $retweet) {
                $retweets[$retweet['remote_id']] = $retweet;
            }
        }
        return $retweets;
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
						DATE_FORMAT(created_at, '%Y-%m-%d') AS `date`,
						COUNT(*) AS `number_of_posts`
					FROM
						{$this->tableName}
					WHERE
						created_at >= :start
						AND created_at <= :end
						AND presence_id = :id
						".($ownPostsOnly ? 'AND posted_by_presence = 1' : '')."
					GROUP BY
						DATE_FORMAT(created_at, '%Y-%m-%d')
				) AS posts
				LEFT JOIN (
					SELECT
						DATE_FORMAT(p.created_at, '%Y-%m-%d') AS `date`,
						COUNT(sl.id) AS `number_of_links`,
						SUM(d.is_bc) AS `number_of_bc_links`
					FROM
						{$this->tableName} AS p
						LEFT JOIN status_links AS sl ON (p.id = sl.status_id AND sl.type = 'sina_weibo')
						LEFT JOIN domains AS d ON (sl.domain = d.domain)
					WHERE
						p.created_at >= :start
						AND p.created_at <= :end
						AND p.presence_id = :id
						".($ownPostsOnly ? 'AND p.posted_by_presence = 1' : '')."
					GROUP BY
						DATE_FORMAT(p.created_at, '%Y-%m-%d')
				) AS links ON (posts.date = links.date)
			ORDER BY
				`date`
		");
		$stmt->execute(array(
			':id'		=> $presence->getId(),
			':start'	=> $start->format('Y-m-d H:i:s'),
			':end'	=> $end->format('Y-m-d H:i:s')
		));
		$ret = $stmt->fetchAll(PDO::FETCH_ASSOC);

		return count($ret) ? $ret : null;
	}

	protected function parseStatus($status)
	{
        $count = 0;
		$statusExists = $this->db->prepare("SELECT EXISTS(SELECT 1 FROM {$this->tableName} WHERE `remote_id` = ?)");
		$statusExists->execute(array($status['idstr']));
		if($statusExists->fetchColumn() == 0){
			$id = $this->saveStatus($status);
            $count++;
			$this->findAndSaveLinks($status['text'], $id);
			if (array_key_exists('retweeted_status', $status)) {
				$s = $status['retweeted_status'];
				Model_PresenceFactory::setDatabase($this->db);
				$presence = null;
				if (array_key_exists('user', $s)) { //apparently some retweets don't have a user (??) so, check to prevent errors
					$presence = Model_PresenceFactory::getPresenceByHandle($s['user']['profile_url'], $this->type);
				}
				$s['posted_by_presence'] = $presence ? 1 : 0;
				$s['presence_id'] = $presence ? $presence->getId() : null;
				$count += $this->parseStatus($s);
			}
		}
        return $count;
	}

	protected function saveStatus($status)
	{
		$stmt = $this->db->prepare("
			INSERT INTO `{$this->tableName}`
			(`remote_id`, `text`, `presence_id`, `remote_user_id`, `created_at`, `picture_url`,
				`posted_by_presence`, `included_retweet`, `repost_count`, `comment_count`, `attitude_count`)
			VALUES
			(:remote_id, :text, :presence_id, :remote_user_id, :created_at, :picture_url,
				:posted_by_presence, :included_retweet, :repost_count, :comment_count, :attitude_count)
		");
		$args = array(
			':remote_id'			=> $status['idstr'],
			':text'					=> $status['text'],
			':presence_id'			=> $status['presence_id'],
			':remote_user_id'		=> array_key_exists('user', $status) ? $status['user']['idstr'] : 0,
			':created_at'			=> date_format(date_create($status['created_at']), 'Y-m-d H:i:s'),
			':picture_url'			=> array_key_exists('original_pic', $status) ? $status['original_pic'] : null,
			':posted_by_presence'	=> $status['posted_by_presence'],
			':included_retweet'		=> array_key_exists('retweeted_status', $status) ? $status['retweeted_status']['idstr'] : null,
			':repost_count'			=> array_key_exists('reposts_count', $status) ? $status['reposts_count'] : 0,
			':comment_count'		=> array_key_exists('comments_count', $status) ? $status['comments_count'] : 0,
			':attitude_count'		=> array_key_exists('attitudes_count', $status) ? $status['attitudes_count'] : 0
		);
		$stmt->execute($args);
		return $this->db->lastInsertId();
	}

	protected function findAndSaveLinks($message, $id)
	{
		$stmt = $this->db->prepare("INSERT INTO status_links (`type`, `status_id`, `url`, `domain`) VALUES (:type, :status_id, :url, :domain)");
		$domainstmt = $this->db->prepare("INSERT IGNORE INTO domains (domain) VALUES (?)");
		if (preg_match_all('@((https?://[\w\d-_]+(\.[\w\d-_]+)+(/[\w\d-_]+)*/?[^\s]*)|(^|\s)([\w\d-_]+(\.[\w\d-_]+){2,}(/[\w\d-_]+)*/?[^\s]*))(\s|$)@', $message, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$fullurl = $match[0];
				$finalPart = $match[4];
                if ($finalPart) {
    				$finalStart = strpos($fullurl, $finalPart);
                } else {
                    $finalStart = strlen($fullurl);
                }
				$url = substr($fullurl, 0, $finalStart + strlen($finalPart));
				try {
					$url = Util_Http::resolveUrl($url);
				} catch (RuntimeException $e) {
					continue; //something is wrong with this URL, so act like it isn't there
				}
				$domain = parse_url($url, PHP_URL_HOST);
				$stmt->execute(array(
					':type'			=> 'sina_weibo',
					':status_id'	=> $id,
					':url'			=> $url,
					':domain'		=> $domain
				));
				//insert domain (if not already exists)
				$domainstmt->execute(array($domain));
				$this->db->query("ALTER TABLE domains AUTO_INCREMENT=1"); //reset auto_increment because insert ignore will increment the auto_increment value even when no rows are inserted.
			}
		}
	}

	public function updateMetadata(Model_Presence $presence) {
		//test if user exists
		$ret = $this->connection->show_user_by_name($presence->getHandle());
		if (!is_array($ret)) {
			//something went really wrong
			throw new RuntimeException('No data received');
		}
		if (array_key_exists('error_code', $ret)) {
			switch ($ret['error_code']) {
				case 20003:
                    throw new Exception('User does not exist: ' . $presence->getHandle());
				case 10006:
					throw new Exception("{$ret['error_code']} - {$ret['error']}");
				default:
					throw new LogicException("Unknown error code {$ret['error_code']} encountered.");
			}
		}

        $presence->type = $this->type;
		$presence->uid = $ret['idstr'];
		$presence->image_url = $ret['profile_image_url'];
		$presence->name = $ret['name'];
		$presence->page_url = self::BASEURL.$ret['profile_url'];
		$presence->popularity = $ret['followers_count'] != 0 ? $ret['followers_count'] : $presence->popularity ;

		$presence->sina_weibo_engagement = $this->calculateSinaWeiboEngagement($presence);
	}


	public static function getMidForPostId($postId)
	{
		$mid = '';
		$postId = (string) $postId;
		while (strlen($postId) >= 7) {
			$part = substr($postId, strlen($postId) - 7);
			$postId = substr($postId, 0, strlen($postId) - 7);
			$mid = Util_Base62::base10to62($part) . $mid;
		}
		if (strlen($postId)) {
			//do the remaining chars
			$mid = Util_Base62::base10to62($postId).$mid;
		}
		return $mid;
	}

    public function getResponseData(Model_Presence $presence, DateTime $start, DateTime $end)
    {
        return array();
    }

	private function calculateSinaWeiboEngagement(Model_Presence $presence)
	{
		$now = new DateTime();
		$then = clone $now;
		$then->modify("-1 week");

		$query = new Outlandish\SocialMonitor\Engagement\Query\WeightedSinaWeiboEngagementQuery($this->db);
		$metric = new Outlandish\SocialMonitor\Engagement\EngagementMetric($query);

		return $metric->get($presence->getId(), $now, $then);
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
			$status->message = $r['text'];
			$status->created_time = $r['created_at'];
			$status->permalink = Provider_SinaWeibo::BASEURL . $r['remote_user_id'] . '/' . Provider_SinaWeibo::getMidForPostId($r['remote_id']);
			$presence = Model_PresenceFactory::getPresenceById($r['presence_id']);
			$status->presence_id = $r['presence_id'];
			$status->presence_name = $presence ? $presence->getName() : '';
			$status->engagement = [
				'shares' => $r['repost_count'],
				'likes' => $r['attitude_count'],
				'comments' => $r['comment_count'],
				'comparable' => (($r['attitude_count'] + $r['comment_count'] * 4 + $r['repost_count'] * 7) / 12)
			];
			$status->icon = Enum_PresenceType::SINA_WEIBO()->getSign();
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
		return new EngagementScore('Sina Weibo engagement score', 'sina-weibo', $presence->getSinaWeiboEngagement());
	}

}