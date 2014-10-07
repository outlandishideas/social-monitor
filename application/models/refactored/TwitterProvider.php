<?php


class NewModel_TwitterProvider extends NewModel_iProvider
{
	protected $connection = null;

	protected $tableName = 'twitter_tweets';

	public function __construct(PDO $db) {
		parent::__construct($db);
	}

	public function fetchData(NewModel_Presence $presence)
	{
		return array();
	}


	public function getHistoricData(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
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
			NewModel_PresenceType::FACEBOOK, //type
			"twitter_presence_test", //handle
			"00000000", //uid
			"http://", //image_url
			"TwitterPresenceTest", //name
			"http://", //page_url
			0, //popularity
			gmdate('Y-m-d H:i:s') //last_updated
		);
	}
}