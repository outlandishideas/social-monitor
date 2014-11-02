<?php

require_once(__DIR__.'/../../../lib/sina_weibo/sinaweibo.php');

class NewModel_SinaWeiboProvider extends NewModel_iProvider
{
	const BASEURL = 'http://www.weibo.com/';

	protected $connection = null;

	public function __construct(PDO $db) {
		parent::__construct($db);
		$this->connection = new SaeTClientV2('1247980630', 'cfcd7c7170b70420d7e1c00628d639c2', '2.00cBChiFjjAm3C216d675d51UwGFRE');
		if (!array_key_exists('REMOTE_ADDR', $_SERVER)) {
			$this->connection->set_remote_ip('127.0.0.1');
		}
		$this->type = NewModel_PresenceType::SINA_WEIBO();
        $this->tableName = 'sina_weibo_posts';
	}

	public function fetchStatusData(NewModel_Presence $presence)
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
                if (!$s['user'] || $s['user']['profile_url'] != $presence->getHandle()) {
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

	public function getHistoricStream (
		NewModel_Presence $presence,
		\DateTime $start,
		\DateTime $end,
		$search = null,
		$order = null,
		$limit = null,
		$offset = null
	) {
		$ret = array();
		$sql = "
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
								`created_at` >= :start
								AND `created_at` <= :end
								AND `presence_id` = :id
						)
						AND type = 'sina_weibo'
					GROUP BY
						status_id
				) AS l ON (p.id = l.status_id)
			WHERE
				p.`created_at` >= :start
				AND p.`created_at` <= :end
				AND p.`presence_id` = :id
		";
		$args = array(
			':start'	=> $start->format('Y-m-d H:i:s'),
			':end'	=> $end->format('Y-m-d H:i:s'),
			':id'		=> $presence->getId()
		);
		if (!is_null($search)) {
			$sql .= ' AND `text` LIKE :search';
			$args[':search'] = '%'.$search.'%';
		}
		if (!is_null($order) && count($order) > 0) {
			$ordering = array();
			foreach ($order as $column=>$dir) {
				switch ($column) {
					case 'date':
						$column = 'created_at';
						break;
					default:
						$column = 'created_at';
						break;
				}
				$ordering[] = $column . ' ' . $dir;
			}
			$sql .= ' ORDER BY '.implode(',', $ordering);
		}
		if (!is_null($limit)) {
			if (is_null($offset)) $offset = 0;
			$sql .= ' LIMIT '.$offset.','.$limit;
		}
		$stmt = $this->db->prepare($sql);
		$stmt->execute($args);
		$ret = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$ids = array();
		foreach ($ret as $r) {
			$ids[] = $r['remote_id'];
		}

		//get retweets
		$stmt = $this->db->prepare("
			SELECT * FROM {$this->tableName} WHERE `remote_id` IN (
				".implode(',', array_fill(0, count($ids), '?'))."
			)
		");
		$stmt->execute($ids);
		$r = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$retweets = array();
		foreach ($r as $retweet) {
			$retweets[$retweet['remote_id']] = $retweet;
		}

		//add retweets and links to posts
		foreach ($ret as &$r) {
			if (!is_null($r['included_retweet']) && array_key_exists($r['included_retweet'], $retweets)) {
				$r['included_retweet'] = $retweets[$r['included_retweet']];
			}
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
						DATE_FORMAT(created_at, '%Y-%m-%d') AS `date`,
						COUNT(*) AS `number_of_posts`
					FROM
						{$this->tableName}
					WHERE
						created_at >= :start
						AND created_at <= :end
						AND presence_id = :id
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
				NewModel_PresenceFactory::setDatabase($this->db);
				$presence = NewModel_PresenceFactory::getPresenceByHandle($s['user']['profile_url'], $this->type);
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
			':remote_id'				=> $status['idstr'],
			':text'						=> $status['text'],
			':presence_id'				=> $status['presence_id'],
			':remote_user_id'			=> $status['user']['idstr'],
			':created_at'				=> date_format(date_create($status['created_at']), 'Y-m-d H:i:s'),
			':picture_url'				=> array_key_exists('original_pic', $status) ? $status['original_pic'] : null,
			':posted_by_presence'	=> $status['posted_by_presence'],
			':included_retweet'		=> array_key_exists('retweeted_status', $status) ? $status['retweeted_status']['idstr'] : null,
			':repost_count'			=> $status['reposts_count'],
			':comment_count'			=> $status['comments_count'],
			':attitude_count'			=> $status['attitudes_count']
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

	public function updateMetadata(NewModel_Presence $presence) {
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
				default:
					throw new LogicException("Unknown error code {$ret['error_code']} encountered.");
			}
		}

        $presence->type = $this->type;
		$presence->uid = $ret['idstr'];
		$presence->image_url = $ret['profile_image_url'];
		$presence->name = $ret['name'];
		$presence->page_url = self::BASEURL.$ret['profile_url'];
		$presence->popularity = $ret['followers_count'];
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
}