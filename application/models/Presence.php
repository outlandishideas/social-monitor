<?php

include APP_ROOT_PATH."/lib/facebook/facebook.php";

class Model_Presence extends Model_Base {
	protected static $tableName = 'presences';
	protected static $sortColumn = 'handle';

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
				$data = Util_Facebook::pageInfo($this->handle);
				if (!$data) {
					throw new RuntimeException('Facebook page not found: ' . $this->handle);
				}
				$this->uid = $data['page_id'];
				$this->image_url = $data['pic_square'];
				$this->name = $data['name'];
				$this->page_url = $data['page_url'];
				$this->popularity = $data['fan_count'];
				break;
			case self::TYPE_TWITTER:
				try {
					$data = Util_Twitter::userInfo($this->handle);
				} catch (Exception_TwitterNotFound $e) {
					throw new Exception('Twitter user not found: ' . $this->handle);
				}
				$this->uid = $data->id_str;
				$this->image_url = $data->profile_image_url;
				$this->name = $data->name;
				$this->page_url = 'http://www.twitter.com/' . $data->screen_name;
				$this->popularity = $data->followers_count;
				break;
		}
	}

	public function getTypeLabel() {
		return ucfirst($this->type);
	}

	public function updateStatuses() {
		$fetchCount = new Util_FetchCount(0, 0);
		switch($this->type) {
			case self::TYPE_FACEBOOK:
				$fetchCount->type = 'post';
				$posts = Util_Facebook::pagePosts($this->uid);
//				print_r($posts);
				//todo: save posts to database
				break;
			case self::TYPE_TWITTER:
				$fetchCount->type = 'tweet';
				$stmt = $this->_db->prepare('SELECT tweet_id FROM twitter_tweets WHERE presence_id = :id ORDER BY created_time DESC LIMIT 1');
				$stmt->execute(array(':id'=>$this->id));
				$lastTweetId = $stmt->fetchColumn();
				$tweets = Util_Twitter::userTweets($this->uid, $lastTweetId);
				$tweetData = array();
				while ($tweets) {
					$tweet = array_shift($tweets);
					$parsedTweet = Util_Twitter::parseTweet($tweet);
					$tweetData[$tweet->id_str] = array(
						'tweet_id' => $tweet->id_str,
						'presence_id' => $this->id,
						'text_expanded' => $parsedTweet['text_expanded'],
						'created_time' => gmdate('Y-m-d H:i:s', strtotime($tweet->created_at)),
						'retweet_count' => $tweet->retweet_count,
						'html_tweet' => $parsedTweet['html_tweet'],
						'in_reply_to_user_uid' => $tweet->in_reply_to_user_id_str,
						'in_reply_to_status_uid' => $tweet->in_reply_to_status_id_str
					);
				}
				$fetchCount->fetched += count($tweetData);
				$fetchCount->added += $this->insertData('twitter_tweets', $tweetData);
				break;
		}
		return $fetchCount;
	}
}