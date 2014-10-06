<?php

require_once(__DIR__.'/../../../lib/sina_weibo/sinaweibo.php');

class Model_SinaWeiboProvider extends Model_iProvider
{
	const BASEURL = 'http://www.weibo.com/';

	protected $connection = null;

	protected $tableName = 'sina_weibo_stream';

	public function __construct(PDO $db) {
		parent::__construct($db);
		$this->connection = new SaeTClientV2('1247980630', 'cfcd7c7170b70420d7e1c00628d639c2', '2.00cBChiFjjAm3C216d675d51UwGFRE');
		if (!array_key_exists('REMOTE_ADDR', $_SERVER)) {
			$this->connection->set_remote_ip('127.0.0.1');
		}
	}

	public function fetchData(Model_Presence $presence)
	{
		return array();
	}


	public function getHistoricData(Model_Presence $presence, \DateTime $start, \DateTime $end)
	{
		return null;
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