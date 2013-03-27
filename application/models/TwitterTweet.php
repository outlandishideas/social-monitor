<?php

class Model_TwitterTweet extends Model_StatusBase {
	protected $_tableName = 'twitter_tweets', $_sortColumn = 'id';
	
	// does a substring replacement using the indices for choosing the substring range
	protected static function replaceTweetSubstring($originalText, $replacement, $indices) {
		return
			mb_substr($originalText, 0, $indices[0], 'utf-8') .
			$replacement .
			mb_substr($originalText, $indices[1], 9999, 'utf-8');
	}

	public static function parseTweet($tweet) {

		$htmlTweet = $tweet->text;
		$expandedText = $tweet->text;

		if (array_key_exists('entities', $tweet)) {
			$entities = array();
			foreach (array('hashtags', 'urls', 'user_mentions'/*, 'media'*/) as $entityType) {
				if (!empty($tweet->entities->$entityType)) {
					foreach ($tweet->entities->$entityType as $entity) {
						$entity->entityType = $entityType;
						$entities[] = $entity;
					}
				}
			}
			// reverse sort by start index
			usort($entities, function ($a, $b) {
				return (int)$a->indices[0] > (int)$b->indices[0] ? -1 : 1;
			});
			// make all of the substitutions
			foreach ($entities as $entity) {
				switch ($entity->entityType) {
					case 'hashtags':
						$replace = '<a href="https://twitter.com/#!/search/%23'.$entity->text.'" target="_blank">#'.$entity->text.'</a>';
						$htmlTweet = self::replaceTweetSubstring($htmlTweet, $replace, $entity->indices);
						break;
					case 'urls':
						// linkify urls, but use the full url (not t.co)
						//display_url is sometimes missing and expanded_url is sometimes null
						if (empty($entity->display_url)) $entity->display_url = $entity->url;
						if (empty($entity->expanded_url)) $entity->expanded_url = $entity->url;
						$replace = '<a href="' . $entity->expanded_url . '" target="_blank">' . $entity->display_url . '</a>';
						$htmlTweet = self::replaceTweetSubstring($htmlTweet, $replace, $entity->indices);
						$expandedText = self::replaceTweetSubstring($expandedText, $replace, $entity->indices);
						break;
					case 'user_mentions':
						$replace = '<a href="https://twitter.com/#!/' . $entity->screen_name . '" target="_blank">@' . $entity->screen_name . '</a>';
						$htmlTweet = self::replaceTweetSubstring($htmlTweet, $replace, $entity->indices);
						break;
//					case 'media':
//						$replacement = '<a href="' . $entity->url . '" class="media">' . $entity->display_url . '</a>';
//						break;
				}
			}
		}

		return array(
			'html_tweet' => $htmlTweet,
			'text_expanded' => $expandedText
		);

	}

	public static function getTwitterUrl($screen_name, $tweetId) {
		return'https://twitter.com/#!/'.$screen_name.'/status/'.$tweetId;
	}

}

?>