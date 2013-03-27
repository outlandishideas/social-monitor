<?php

require_once APP_ROOT_PATH.'/lib/twitteroauth/OAuth.php';
require_once APP_ROOT_PATH.'/lib/twitteroauth/twitteroauth.php';

define('TWITTER_SEARCH_ENDPOINT', 'http://search.twitter.com/search.json');

class Model_TwitterToken extends Model_Base {
	protected static $tableName = 'twitter_tokens';

	public function getConnection() {
		$config = Zend_Registry::get('config');

		$connection = new TwitterOAuth($config->twitter->consumer_key,$config->twitter->consumer_secret,
				$this->oauth_token, $this->oauth_token_secret);
		$connection->host = 'https://api.twitter.com/1.1/';

		return $this->connection = $connection;
	}

	public function getTwitterUser() {
		$this->twitterUser = Model_TwitterUser::fetchById($this->twitter_user_id);
		return  $this->twitterUser;
	}

	public function apiRequest($path, $args = array()) {
		//search API is a bit different
		$result = $this->connection->get($path, $args);
		$resultCode = $this->connection->http_code;
		$this->connection->http_code = null;

		//update rate limit from headers
		if (isset($this->connection->http_header['x_rate_limit_limit'])) {
			$rateLimit = array();
			foreach (array('limit', 'remaining', 'reset') as $type) {
				$rateLimit[$type] = $this->connection->http_header['x_rate_limit_'.$type];
			}
			$this->setRateLimit($path, $rateLimit);
			$this->save();
		}

		//handle response
		switch ($resultCode) {
			case Model_TwitterStatusCodes::OK :
				return $result;
			case Model_TwitterStatusCodes::NOT_FOUND :
				throw new Exception_TwitterNotFound('Twitter API Error: Not found', $resultCode, $path, $result->errors);
			default:
				$errors = isset($result->errors) ? $result->errors : array();
				throw new Exception_TwitterApi('Twitter API Error', $resultCode, $path, $errors);
		}
	}

	public static function getAuthorizeUrl($callback_url = null) {
		$config = Zend_Registry::get('config');

		$connection = new TwitterOAuth($config->twitter->consumer_key, $config->twitter->consumer_secret);
		$connection->host = 'https://api.twitter.com/1.1/';
		$temp_token = $connection->getRequestToken($callback_url);
		$_SESSION['temp_token'] = $temp_token;

		return $connection->getAuthorizeURL($temp_token, false);
	}

	public static function getToken($oauth_verifier) {
		$config = Zend_Registry::get('config');

		$temp_token = $_SESSION['temp_token'];
		unset($_SESSION['temp_token']);

		$connection = new TwitterOAuth($config->twitter->consumer_key,$config->twitter->consumer_secret,
			$temp_token['oauth_token'], $temp_token['oauth_token_secret']);
		$connection->host = 'https://api.twitter.com/1.1/';
		$the_token = $connection->getAccessToken($oauth_verifier);

		$token = Model_TwitterToken::fetchBy('twitter_user_id', $the_token['user_id']);
		if (!$token) {
			$token = new self();
		}

		$token->oauth_token = $the_token['oauth_token'];
		$token->oauth_token_secret = $the_token['oauth_token_secret'];
		$token->twitter_user_id = $the_token['user_id'];

		return $token;
	}

	public static function getCurrentUserToken() {
		$auth = Zend_Auth::getInstance();
		$currentUser = Model_User::fetchById($auth->getIdentity());
		return $currentUser->twitterToken;
	}

	public function setRateLimit($path, $rateLimit) {
		$rateLimits = array_merge($this->getRateLimit(), array($path => $rateLimit));
		$this->rate_limits = json_encode($rateLimits);
	}

	public function getRateLimit($path = null, $type = null) {
		//unpack rates
		$rateLimits = $this->rate_limits ? json_decode($this->rate_limits, true) : array();

		//reset any limits with reset time in the past
		foreach ($rateLimits as $p => $limits) {
			if ($limits['reset'] < time()) {
				$rateLimits[$p]['remaining'] = $limits['limit'];
				$rateLimits[$p]['reset'] = 0;
			}
		}

		//if no path specified then return all paths
		if (!$path) {
			return $rateLimits;
		}

		//check if record for path doesn't exist or is expired
		if (!isset($rateLimits[$path])) {
			return array();
		}

		//return all rates or just for requested type
		return $type ? $rateLimits[$path][$type] : $rateLimits[$path];
	}
}