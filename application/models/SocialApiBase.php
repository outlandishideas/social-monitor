<?php

// base class for facebook pages and twitter lists/searches
abstract class Model_SocialApiBase extends Model_Base {

	// number of seconds in each pre-calculated bucket
	public static $bucketSizes = array(
		'bucket_half_hour' => 1800, // 30*60
		'bucket_4_hours' => 14400, // 4*60*60
		'bucket_12_hours' => 43200, // 12*60*60
		'bucket_day' => 86400 // 24*60*60
	);

	public function getCampaign() {
		$this->campaign = Model_Campaign::fetchById($this->campaign_id);
		return $this->campaign;
	}

	public static function getStatusType() {
		$classname = get_called_class();
		return (strpos($classname, 'Twitter') !== false ? 'tweet' : 'post');
	}
	
	// gets the appropriate status (tweet/post) table and corresponding where clause(s)
	protected static function getStatusTableQuery($modelIds) {
		$statusType = self::getStatusType();
		if ($statusType == 'tweet') {
			$classname = get_called_class();
			if ($classname == 'Model_TwitterSearch') {
				$parentType = 'search';
			} else {
				$parentType = 'list';
			}
			$table = 'twitter_tweets';
		} else {
			$parentType = 'page';
			$table = 'facebook_stream';
		}
		
		if (!is_array($modelIds)) {
			$modelIds = array($modelIds);
		}

		$args = array();
		$modelPlaceholders = array();
		foreach ($modelIds as $index=>$id) {
			$placeholder = ":{$parentType}_{$index}";
			$args[$placeholder] = $id;
			$modelPlaceholders[] = $placeholder;
		}
		$modelIds = implode(',', $modelPlaceholders);
		if ($statusType == 'tweet') {
			$where = "parent_type = :parent_type AND parent_id IN ($modelIds)";
			$args[':parent_type'] = $parentType;
		} else {
			$where = "facebook_page_id IN ($modelIds)";
		}
		
		return array($args, $table, $where);
	}
}