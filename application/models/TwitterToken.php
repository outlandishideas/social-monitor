<?php

require_once APP_ROOT_PATH.'/lib/twitteroauth/OAuth.php';
require_once APP_ROOT_PATH.'/lib/twitteroauth/twitteroauth.php';

class Model_TwitterToken extends Model_Base {
	protected static $tableName = 'twitter_tokens';

	protected $rateLimits = array();

	public function getConnection() {
		$config = Zend_Registry::get('config');

		$connection = new TwitterOAuth($config->twitter->consumer_key,$config->twitter->consumer_secret,
				$config->twitter->access_token, $config->twitter->access_token_secret);
		$connection->host = 'https://api.twitter.com/1.1/';

		return $this->connection = $connection;
	}

	public function apiRequest($path, $args = array()) {
		if (!empty($this->rateLimits[$path]) && $this->rateLimits[$path]['remaining'] == '0') {
			throw new Exception_TwitterRateLimit('Twitter API Error: Rate limit exceeded', Model_TwitterStatusCodes::TOO_MANY_REQUESTS, $path, $this->rateLimit[$path]);
		}

		$result = $this->connection->get($path, $args);
		$resultCode = $this->connection->http_code;
		$this->connection->http_code = null;

		if (isset($this->connection->http_header['x_rate_limit_limit'])) {
			$rateLimit = array();
			foreach (array('limit', 'remaining', 'reset') as $type) {
				$rateLimit[$type] = $this->connection->http_header['x_rate_limit_'.$type];
			}
			$this->rateLimits[$path] = $rateLimit;
		}

		//handle response
		switch ($resultCode) {
			case Model_TwitterStatusCodes::OK :
				return $result;
			case Model_TwitterStatusCodes::NOT_FOUND :
				throw new Exception_TwitterNotFound('Twitter API Error: Not found', $resultCode, $path, $result->errors);
			case Model_TwitterStatusCodes::TOO_MANY_REQUESTS :
				throw new Exception_TwitterRateLimit('Twitter API Error: Rate limit exceeded', $resultCode, $path, $result->errors);
			default:
				$errors = isset($result->errors) ? $result->errors : array();
				throw new Exception_TwitterApi('Twitter API Error', $resultCode, $path, $errors);
		}
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

	public function save() {
		// don't do anything. Token can't be saved
	}
}