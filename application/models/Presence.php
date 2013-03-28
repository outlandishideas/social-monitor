<?php

include APP_ROOT_PATH."/lib/facebook/facebook.php";

class Model_Presence extends Model_Base {
	protected static $tableName = 'presences';
	protected static $sortColumn = 'handle';

	private static $_fb;

	const TYPE_FACEBOOK = 'facebook';
	const TYPE_TWITTER = 'twitter';

	public static function fetchAllTwitter() {
		return self::fetchAll('type = :type', array(':type'=>self::TYPE_TWITTER));
	}

	public static function fetchAllFacebook() {
		return self::fetchAll('type = :type', array(':type'=>self::TYPE_FACEBOOK));
	}

	public function getLabel() {
		return $this->name ?: $this->handle;
	}

	public function updateInfo() {
		switch($this->type) {
			case self::TYPE_FACEBOOK:
				$fql = 'SELECT page_id, name, username, pic_square, page_url, fan_count
					FROM page WHERE username = "' . $this->handle.'"';
				$data = $this->facebookQuery($fql);
				if (!$data) {
					throw new RuntimeException('Facebook page not found: ' . $this->handle);
				}
				$data = $data[0];
				$this->uid = $data['page_id'];
				$this->image_url = $data['pic_square'];
				$this->name = $data['name'];
				$this->page_url = $data['page_url'];
				$this->popularity = $data['fan_count'];
				break;
			case self::TYPE_TWITTER:
				$token = new Model_TwitterToken();
				$data = $token->apiRequest('users/show', array('screen_name'=>$this->handle));
				$this->uid = $data->id_str;
				$this->image_url = $data->profile_image_url;
				$this->name = $data->name;
				$this->page_url = 'http://www.twitter.com/' . $data->screen_name;
				$this->popularity = $data->followers_count;
				break;
		}
		$this->last_updated = gmdate('Y-m-d H:i:s');
	}

	public function getTypeLabel() {
		return ucfirst($this->type);
	}

	/**
	 * Query Facebook API
	 * @param $fql string FQL query string
	 * @return mixed
	 */
	public function facebookQuery($fql) {
		$fb = self::fb();
		$ret = $fb->api( array(
			'method' => 'fql.query',
			'query' => $fql,
		));
		return $ret;
	}

	/**
	 * @static
	 * @return mixed instance of Facebook API object
	 */
	public static function fb() {

		if (!isset(self::$_fb)) {
			$config = Zend_Registry::get('config');
			self::$_fb = new Facebook(array(
				'appId' => $config->facebook->appId,
				'secret' => $config->facebook->secret
			));
		}

		return self::$_fb;
	}
}