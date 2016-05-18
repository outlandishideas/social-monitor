<?php

namespace Outlandish\SocialMonitor\Adapter;

use Exception_TwitterNotFound;
use Model_TwitterToken;
use Outlandish\SocialMonitor\Models\Status;
use Outlandish\SocialMonitor\Models\PresenceMetadata;
use Outlandish\SocialMonitor\Models\Tweet;

class TwitterAdapter extends AbstractAdapter
{
	// todo: Use HWIOAuthBundle instead of hard-coded twitteroauth libraries

	protected $_token;
	protected $fetchPerPage;

	public function __construct($fetchPerPage)
	{
		$this->fetchPerPage = $fetchPerPage;
	}

	/**
	 * @static
	 * @return Model_TwitterToken instance of Twitter Token object
	 */
	protected function token()
	{
		if (!$this->_token) {
			$this->_token = new Model_TwitterToken();
		}

		return $this->_token;
	}

	/**
	 * @param $handle
	 * @return PresenceMetadata
	 * @throws Exception_TwitterNotFound
	 */
	public function getMetadata($handle)
	{

		try {
			$data = $this->token()->apiRequest('users/show', array('screen_name' => $handle));
		} catch (Exception_TwitterNotFound $e) {
			throw new Exception_TwitterNotFound('Twitter user not found: ' . $handle, $e->getCode(), $e->getPath(), $e->getErrors());
		}

		$metadata = new PresenceMetadata();
		$metadata->uid = $data->id_str;
		$metadata->image_url = $data->profile_image_url;
		$metadata->name = $data->name;
		$metadata->page_url = 'https://www.twitter.com/' . $data->screen_name;
		$metadata->popularity = $data->followers_count;

		return $metadata;

	}

	/**
	 * Gets an array of tweets that mention the user
	 * @param $userHandle
	 * @param null $minTweetId
	 * @param null $maxTweetId
	 * @return array
	 */
	protected function userMentions($userHandle, $minTweetId = null, $maxTweetId = null)
	{
		$tweets = array();
		if ($userHandle) {
			$token = $this->token();
			$args = array(
				'q' => '@' . $userHandle,
				'count' => $this->fetchPerPage,
				'exclude_replies' => true,
				'include_rts' => false,
				'trim_user' => true
			);
			if ($minTweetId) {
				// since_id is exclusive
				$args['since_id'] = $minTweetId;
			}
			if ($maxTweetId) {
				$args['max_id'] = $maxTweetId;
			}

			do {
				$result = $token->apiRequest('search/tweets', $args);
				$tweets = array_merge($tweets, $result->statuses);

				// if we have a minimum tweet id, and we fetch the exact number we asked for, we likely need to fill in the gap using another request
				$repeat = ($minTweetId && count($result->statuses) == $args['count']);

				if ($repeat) {
					$lowestId = min(array_map(function ($t) {
						return $t->id_str;
					}, $result->statuses));
					//TODO check whether system is 64-bit
					// max_id is inclusive, so need to subtract 1
					$args['max_id'] = function_exists('bcsub') ? bcsub($lowestId, 1) : $lowestId - 1;
				}
			} while ($repeat);
		}
		return $tweets;
	}

	/**
	 * Gets an array of tweets for the given user
	 * @param $userId
	 * @param null $minTweetId
	 * @param null $maxTweetId
	 * @return array
	 */
	protected function userTweets($userId, $minTweetId = null, $maxTweetId = null)
	{
		$tweets = array();
		if ($userId) {
			$token = self::token();
			$args = array(
				'user_id' => $userId,
				'count' => $this->fetchPerPage,
				'exclude_replies' => false,
				'include_rts' => true,
				'trim_user' => true
			);
			if ($minTweetId) {
				// since_id is exclusive
				$args['since_id'] = $minTweetId;
			}
			if ($maxTweetId) {
				$args['max_id'] = $maxTweetId;
			}

			do {
//	    		print_r($args);
//	    		echo "\n";
				$result = $token->apiRequest('statuses/user_timeline', $args);
				$tweets = array_merge($tweets, $result);

//	    		$this->logApiResult($result);

				// if we have a minimum tweet id, and we fetch the exact number we asked for, we likely need to fill in the gap using another request
				$repeat = ($minTweetId && count($result) == $args['count']);

				if ($repeat) {
					$lowestId = min(array_map(function ($t) {
						return $t->id_str;
					}, $result));
					//TODO check whether system is 64-bit
					// max_id is inclusive, so need to subtract 1
					$args['max_id'] = function_exists('bcsub') ? bcsub($lowestId, 1) : $lowestId - 1;
				}
			} while ($repeat);
		}
		return $tweets;
	}

	/**
	 * @param $pageUID
	 * @param string $handle
	 * @param string $since
	 * @return Status[]
	 */
	public function getStatuses($pageUID, $since, $handle = null)
	{

		$tweets = $this->userTweets($pageUID, $since);
		$mentions = $this->userMentions($handle, $since);

		$parsedStatuses = array();

		foreach ($tweets as $raw) {
			$parsedStatuses[] = $this->parseStatus($raw, false, $pageUID);
		}

		foreach ($mentions as $raw) {
			$parsedStatuses[] = $this->parseStatus($raw, true, $pageUID);
		}

		return $parsedStatuses;
	}

	private function parseStatus($raw, $mention, $pageUID)
	{
		$parsedTweet = $this->parseTweet($raw);
		$isRetweet = isset($raw->retweeted_status) && $raw->retweeted_status->user->id == $pageUID;
		$tweet = new Tweet();
		$tweet->id = $raw->id_str;
		$tweet->message = $parsedTweet['text_expanded'];
		$tweet->created_time = gmdate('Y-m-d H:i:s', strtotime($raw->created_at));
		$tweet->share_count = $raw->retweet_count;
		$tweet->html = $parsedTweet['html_tweet'];
		$tweet->in_response_to_user_uid = $raw->in_reply_to_user_id_str;
		$tweet->in_response_to_status_uid = $raw->in_reply_to_status_id_str;
		$tweet->posted_by_owner = !$mention;
		$tweet->needs_response = $mention && !$isRetweet ? 1 : 0;
		$tweet->hashtags = $parsedTweet['hashtags'];
		if (!empty($raw->entities->urls) && !$mention) {
			$tweet->links = array_map(function ($a) {
				return $a->expanded_url;
			}, $raw->entities->urls);
		}
		return $tweet;
	}

	/**
	 * does a substring replacement using the indices for choosing the substring range
	 * @param $originalText string
	 * @param $replacement string
	 * @param $indices array
	 * @return string
	 */
	protected function replaceTweetSubstring($originalText, $replacement, $indices)
	{
		return
			mb_substr($originalText, 0, $indices[0], 'utf-8') .
			$replacement .
			mb_substr($originalText, $indices[1], 9999, 'utf-8');
	}

	/**
	 * Substitutes all of the entities into the tweet, returning an array containing:
	 * - text_expanded: the tweet, with all urls expanded to their original url
	 * - html_tweet: the tweet, with all entities converted to links
	 * @param $tweet
	 * @return array
	 */
	protected function parseTweet($tweet)
	{

		$isRetweet = !empty($tweet->retweeted_status->text);
		$hashTags = array();

		if ($isRetweet) {
			$htmlTweet = $tweet->retweeted_status->text;
			$entities = $tweet->retweeted_status->entities;
		} else {
			$htmlTweet = $tweet->text;
			$entities = $tweet->entities;
		}
		$expandedText = $htmlTweet;

		$allEntities = array();
		foreach (array('hashtags', 'urls', 'user_mentions'/*, 'media'*/) as $entityType) {
			if (!empty($entities->$entityType)) {
				foreach ($entities->$entityType as $entity) {
					$entity->entityType = $entityType;
					$allEntities[] = $entity;
				}
			}
		}
		// reverse sort by start index
		usort($allEntities, function ($a, $b) {
			return (int)$a->indices[0] > (int)$b->indices[0] ? -1 : 1;
		});
		// make all of the substitutions
		foreach ($allEntities as $entity) {
			switch ($entity->entityType) {
				case 'hashtags':
					array_push($hashTags, $entity->text);
					$replace = '<a href="https://twitter.com/search/%23' . $entity->text . '" target="_blank">#' . $entity->text . '</a>';
					$htmlTweet = $this->replaceTweetSubstring($htmlTweet, $replace, $entity->indices);
					break;
				case 'urls':
					// linkify urls, but use the full url (not t.co)
					//display_url is sometimes missing and expanded_url is sometimes null
					if (empty($entity->display_url)) $entity->display_url = $entity->url;
					if (empty($entity->expanded_url)) $entity->expanded_url = $entity->url;
					$replace = '<a href="' . $entity->expanded_url . '" target="_blank">' . $entity->display_url . '</a>';
					$htmlTweet = $this->replaceTweetSubstring($htmlTweet, $replace, $entity->indices);
					$expandedText = $this->replaceTweetSubstring($expandedText, $replace, $entity->indices);
					break;
				case 'user_mentions':
					$replace = '<a href="https://twitter.com/' . $entity->screen_name . '" target="_blank">@' . $entity->screen_name . '</a>';
					$htmlTweet = $this->replaceTweetSubstring($htmlTweet, $replace, $entity->indices);
					break;
//					case 'media':
//						$replacement = '<a href="' . $entity->url . '" class="media">' . $entity->display_url . '</a>';
//						break;
			}
		}

		if ($isRetweet) {
			$rt = 'RT ';
			if (!empty($tweet->retweeted_status->user->screen_name)) {
				$screenName = $tweet->retweeted_status->user->screen_name;
				$rt .= '<a href="https://twitter.com/' . $screenName . '" target="_blank">@' . $screenName . '</a>: ';
			}
			$htmlTweet = $rt . $htmlTweet;
			$expandedText = $rt . $expandedText;
		}

		return array(
			'html_tweet' => $htmlTweet,
			'text_expanded' => $expandedText,
			'hashtags' => array_unique($hashTags)
		);

	}
}