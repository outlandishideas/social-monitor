<?php


class Model_TwitterProvider extends Model_iProvider
{
	protected $connection = null;

	protected $tableName = 'twitter_tweets';

	public function __construct(PDO $db) {
		parent::__construct($db);
	}

	public function fetchData(Model_Presence_New $presence)
	{
		return array();
	}


	public function getHistoricData(Model_Presence_New $presence, \DateTime $start, \DateTime $end)
	{
		return null;
	}


	protected function findAndSaveLinks($streamdatum)
	{
		return 0;
	}

	public function testHandle($handle) {
		//test if user exists
		return array(
			Model_PresenceType::FACEBOOK, //type
			"twitter_presence_test", //handle
			"00000000", //uid
			"http://", //image_url
			"TwitterPresenceTest", //name
			"http://", //page_url
			0  //popularity
		);
	}
}