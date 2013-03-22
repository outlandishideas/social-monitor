<?php

class Model_TwitterList extends Model_TwitterBase {
	protected $_tableName = 'twitter_lists';
	protected $type = 'list';

	public function getLabel() {
		return $this->name;
	}
	
	public function getFetchUrl() {
		$this->fetchUrl = 'lists/statuses';
		return $this->fetchUrl;
	}

	public function getFetchArgsArray() {
		$listBits = explode('/', $this->slug);
		$fetchArgs = array(
			'slug'=>$listBits[1],
			'owner_screen_name'=>$listBits[0],
			'include_entities' => 1,
			'per_page'=>Zend_Registry::get('config')->twitter->list_fetch_per_page
		);
		$this->fetchArgsArray = array($fetchArgs);
		return $this->fetchArgsArray;
	}

	protected function getTweetListFromApiResult($apiResult) {
		return $apiResult;
	}
	
	
}