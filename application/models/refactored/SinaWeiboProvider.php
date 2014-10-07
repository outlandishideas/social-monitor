<?php

require_once(__DIR__.'/../../../lib/sina_weibo/sinaweibo.php');

class NewModel_SinaWeiboProvider extends NewModel_iProvider
{
	const BASEURL = 'http://www.weibo.com/';

	protected $connection = null;

	protected $tableName = 'sina_weibo_posts';
	protected $type = null;
	protected $sign = "fa-weibo";

	public function __construct(PDO $db) {
		parent::__construct($db);
		$this->connection = new SaeTClientV2('1247980630', 'cfcd7c7170b70420d7e1c00628d639c2', '2.00cBChiFjjAm3C216d675d51UwGFRE');
		if (!array_key_exists('REMOTE_ADDR', $_SERVER)) {
			$this->connection->set_remote_ip('127.0.0.1');
		}
		$this->type = NewModel_PresenceType::SINA_WEIBO();
	}

	public function fetchData(NewModel_Presence $presence)
	{
		$stmt = $this->db->prepare("SELECT MAX(`remote_id`) AS `since_id` FROM `{$this->tableName}` WHERE `presence_id` = ".$presence->getId());
		$stmt->execute();
		$data = $stmt->fetch(PDO::FETCH_ASSOC);
		$since_id = $data['since_id'];
		$ret = array();
		$page = 0;
		do {
			$page++;
			$data = $this->connection->friends_timeline($page, 200, $since_id);
			foreach ($data['statuses'] as $s) {
				if ($s['user']['profile_url'] != $presence->getHandle()) continue;
				$s['presence_id'] = $presence->getId();
				$s['posted_by_presence'] = 1;
				$this->parseStatus($s);
				if ($s['user']) {
					$ret['popularity'] = $s['user']['followers_count'];
				}
			}
		} while (count($data['statuses']));
		return $ret;
	}


	public function getHistoricData(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
	{
		return null;
	}

	protected function parseStatus($status)
	{
		$id = $this->saveStatus($status);
		$status['local_id'] = $id;
		$this->findAndSaveLinks($status);
		if (array_key_exists('retweeted_status', $status)) {
			$s = $status['retweeted_status'];
			NewModel_PresenceFactory::setDatabase($this->db);
			$presence = NewModel_PresenceFactory::getPresenceByHandle($s['user']['profile_url'], $this->type);
			$s['posted_by_presence'] = $presence ? 1 : 0;
			$s['presence_id'] = $presence ? $presence->getId() : null;
			$this->parseStatus($s);
		}
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

	protected function findAndSaveLinks($status)
	{
		$text = $status['text'];
		$stmt = $this->db->prepare("INSERT INTO status_links (`type`, `status_id`, `url`, `domain`) VALUES (:type, :status_id, :url, :domain)");
		$domainstmt = $this->db->prepare("INSERT IGNORE INTO domains (domain) VALUES (?)");
		if (preg_match_all('@((https?://[\w\d-_]+(\.[\w\d-_]+)+(/[\w\d-_]+)*/?[^\s]*)|(^|\s)([\w\d-_]+(\.[\w\d-_]+){2,}(/[\w\d-_]+)*/?[^\s]*))(\s|$)@', $text, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$fullurl = $match[0];
				$finalPart = $match[4];
				$finalStart = strpos($fullurl, $finalPart);
				$url = substr($fullurl, 0, $finalStart + strlen($finalPart));
				try {
					$url = Util_Http::resolveUrl($url);
				} catch (RuntimeException $e) {
					continue; //something is wrong with this URL, so act like it isn't there
				}
				$domain = parse_url($url, PHP_URL_HOST);
				$stmt->execute(array(
					':type'			=> 'sina_weibo',
					':status_id'	=> $status['local_id'],
					':url'			=> $url,
					':domain'		=> $domain
				));
				//insert domain (if not already exists)
				$domainstmt->execute(array($domain));
				$this->db->query("ALTER TABLE domains AUTO_INCREMENT=1"); //reset auto_increment because insert ignore will increment the auto_increment value even when no rows are inserted.
			}
		}
	}

	public function testHandle($handle) {
		//test if user exists
		$ret = $this->connection->show_user_by_name($handle);
		if (array_key_exists('error_code', $ret)) {
			switch ($ret['error_code']) {
				case 20003:
					return false;
					break;
				default:
					throw new LogicException("Unknown error code {$ret['error_code']} encountered.");
					break;
			}
		}

		return array(
			NewModel_PresenceType::SINA_WEIBO, //type
			$handle, //handle
			$ret['idstr'], //uid
			$ret['profile_image_url'], //image_url
			$ret['name'], //name
			self::BASEURL.$ret['profile_url'], //page_url
			$ret['followers_count'],  //popularity
			gmdate('Y-m-d H:i:s') //last_updated
		);
	}
}