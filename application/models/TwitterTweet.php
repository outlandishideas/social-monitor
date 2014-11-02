<?php

class Model_TwitterTweet extends Model_Base {
	protected static $tableName = 'twitter_tweets';

	public static function getTwitterUrl($screen_name, $tweetId) {
		return'https://twitter.com/'.$screen_name.'/status/'.$tweetId;
	}

}
