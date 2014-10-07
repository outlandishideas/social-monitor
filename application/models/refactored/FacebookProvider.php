<?php


class Model_FacebookProvider extends Model_iProvider
{
	protected $connection = null;

	protected $tableName = 'facebook_stream';

	public function __construct(PDO $db) {
		parent::__construct($db);
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
		return array(
			Model_PresenceType::FACEBOOK, //type
			$handle, //handle
			"00000000", //uid
			"http://", //image_url
			"FacebookPresenceTest", //name
			"http://", //page_url
			0  //popularity
		);
	}
}