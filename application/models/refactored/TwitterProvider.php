<?php


class NewModel_TwitterProvider extends NewModel_iProvider
{
	protected $connection = null;

	protected $tableName = 'twitter_tweets';
	protected $type = null;

	const KLOUT_API_ENDPOINT = 'http://api.klout.com/v2/';

	public function __construct(PDO $db) {
		parent::__construct($db);
		$this->type = NewModel_PresenceType::TWITTER();
	}

	public function fetchData(NewModel_Presence $presence)
	{
		if (!$presence->uid) {
			throw new Exception('Presence not initialised/found');
		}



		return array();
	}


	public function getHistoricData(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
	{
		return null;
	}


	public function getHistoricStream(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
	{
		return null;
	}


	public function getHistoricStreamMeta(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
	{
		return null;
	}


	protected function findAndSaveLinks($streamdatum)
	{
		return 0;
	}

	public function testHandle($handle) {#

		try {
			$data = Util_Twitter::userInfo($handle);
		} catch (Exception_TwitterNotFound $e) {
			return false;
//			throw new Exception_TwitterNotFound('Twitter user not found: ' . $this->handle, $e->getCode(), $e->getPath(), $e->getErrors());
		}

		// update the klout score (not currently possible for facebook pages)
		//todo: get klout score
//		try {
//			$apiKey = Zend_Registry::get('config')->klout->api_key;
//			$success = $this->updateKloutScore($apiKey);
//			if (!$success) {
//				$this->klout_id = null;
//				$this->updateKloutScore($apiKey);
//			}
//		} catch (Exception $ex) { /* ignore */ }

//		if (!$this->klout_id) {
//			$json = Util_Http::fetchJson(self::KLOUT_API_ENDPOINT . 'identity.json/tw/' . $this->uid . '?key=' . $apiKey);
//			$this->klout_id = $json->id;
//		}
//		if ($this->klout_id) {
//			try {
//				$json = Util_Http::fetchJson(self::KLOUT_API_ENDPOINT . 'user.json/' . $this->klout_id . '?key=' . $apiKey);
//				$this->klout_score = $json->score->score;
//			} catch (RuntimeException $ex) {
//				if ($ex->getCode() == 404) {
//					/* Do Something */
//				}
//			}
//		}

		//test if user exists
		return array(
			NewModel_PresenceType::TWITTER, //type
			$handle, //handle
			$data->id_str, //uid
			$data->profile_image_url, //image_url
			$data->name, //name
			'http://www.twitter.com/' . $data->screen_name, //page_url
			$data->followers_count, //popularity
			gmdate('Y-m-d H:i:s') //last_updated
		);
	}
}