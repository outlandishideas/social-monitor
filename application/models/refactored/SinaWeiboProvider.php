<?php

require_once(__DIR__.'/../../../lib/sina_weibo/sinaweibo.php');

class Model_SinaWeiboProvider extends Model_iProvider
{
	const BASEURL = 'http://www.weibo.com/';

	protected $connection = null;

	protected $tableName = 'sina_weibo_posts';

	public function __construct(PDO $db) {
		parent::__construct($db);
		$this->connection = new SaeTClientV2('1247980630', 'cfcd7c7170b70420d7e1c00628d639c2', '2.00cBChiFjjAm3C216d675d51UwGFRE');
		if (!array_key_exists('REMOTE_ADDR', $_SERVER)) {
			$this->connection->set_remote_ip('127.0.0.1');
		}
	}

	public function fetchData(Model_Presence $presence)
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
				$this->parseStatus($s);
			}
		} while (count($data['statuses']));
		return $ret;
	}


	public function getHistoricData(Model_Presence $presence, \DateTime $start, \DateTime $end)
	{
		return null;
	}

	protected function parseStatus($status)
	{
		//var_dump(date_create($status['created_at'])); exit;
		var_dump($status);exit;
		$status['posted_by_presence'] = 1;
		$this->saveStatus($status);
		if (array_key_exists('retweeted_status', $status)) {
			$s = $status['retweeted_status'];
			$s['posted_by_presence'] = ($s['user']['idstr'] == $status['user']['idstr'] ? 1 : 0);
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
			':comment_count'			=> $status['comment_count'],
			':attitude_count'			=> $status['attitude_count']
		);
		$stmt->execute($args);
	}

	protected function findAndSaveLinks($streamdatum)
	{
		return 0;
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
			Model_PresenceType::SINA_WEIBO, //type
			$handle, //handle
			$ret['idstr'], //uid
			$ret['profile_image_url'], //image_url
			$ret['name'], //name
			self::BASEURL.$ret['profile_url'], //page_url
			$ret['followers_count']  //popularity
		);
	}
}