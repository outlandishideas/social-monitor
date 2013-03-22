<?php

define('OPENAMPLIFY_ENDPOINT', 'http://portaltnx20.openamplify.com/AmplifyWeb_v21/AmplifyThis');

class OpenAmplify {

	public static function extractTopics($type, $string, $obj) {
		$config = Zend_Registry::get('config');

		//openamplify fails with short texts
		if (strlen($string) < $config->openamplify->min_text_length) {
			// echo "$string not long enough\n";
			return array();
		}

		if ($type == 'tweet') {
			$tableName = 'twitter_tweet_topics';
			$idColumnName = 'twitter_tweet_id';
			$objId = $obj->tweet_id;
		} else if ($type == 'post') {
			$tableName = 'facebook_post_topics';
			$idColumnName = 'facebook_stream_id';
			$objId = $obj->id;
		}

		if (!isset($tableName) || !isset($idColumnName)) {
			// echo "$className not handled\n";
			return array();
		}
		
		$args = array(
			'apiKey' => $config->openamplify->api_key,
			'inputText' => $string,
			'analysis' => 'TopTopics',
			'outputFormat' => 'json',
			'OptimiseRespTime' => 'disable'
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, OPENAMPLIFY_ENDPOINT.'?'.http_build_query($args));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		$json = curl_exec($ch); 
		$resultCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($resultCode == 406) {
			//ignore 406 Not Acceptable errors - these are usually the result of a weird tweet
			return array();
		} elseif (!$resultCode) {
			throw new RuntimeException("OpenAmplify request failed for $type $objId (too slow)");
		} elseif ($resultCode != 200) {
			throw new RuntimeException("OpenAmplify request failed for $type $objId (HTTP code $resultCode)");
		} elseif (!$json) {
			throw new RuntimeException('No result from OpenAmplify');
		}
		
		$result = json_decode($json);

		// TODO: can we tell between 'no topics found' and 'daily limit reached'?
		$topics = array();
		$totalSentiment = 0;
		if (isset($result->{'ns1:TopTopicsResponse'}->TopTopicsReturn->Topics->TopTopics)) {
			$topTopics = $result->{'ns1:TopTopicsResponse'}->TopTopicsReturn->Topics->TopTopics;

			if (count($topTopics) > 0) {
				$stopWords = $config->openamplify->stop_words->toArray();
				foreach ($topTopics as $topic) {
					if ($topic->Topic->Name && !in_array(strtolower($topic->Topic->Name), $stopWords)) {
						$topics[] = array(
							$idColumnName => $objId,
							'topic' => $topic->Topic->Name,
							'normalised_topic' => strtolower($topic->Topic->Name), // TODO: should # and @ prefixes be stripped here?
							'importance' => $topic->Topic->Value,
							'polarity' => $topic->Polarity->Mean->Value
						);
						$totalSentiment += $topic->Polarity->Mean->Value;
					}
				}
				
				$obj->insertData($tableName, $topics);
				$obj->average_sentiment = $totalSentiment/count($topTopics);
			}
		}

		return $topics;
	}
}