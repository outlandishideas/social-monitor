<?php

class Model_Campaign extends Model_Base {
	protected $_tableName = 'campaigns', $_sortColumn = 'name';

	public function delete() {
		foreach ($this->networks as $network) $network->delete();
		foreach ($this->twitterLists as $list) $list->delete();
		foreach ($this->twitterSearches as $search) $search->delete();
		foreach ($this->facebookPages as $page) $page->delete();

		$this->_db->prepare('DELETE FROM user_campaigns WHERE campaign_id = ?')->execute(array($this->id));
		$this->_db->prepare('UPDATE users SET last_campaign_id = NULL WHERE last_campaign_id = ?')->execute(array($this->id));

		parent::delete();
	}

	function getTwitterLists() {
		$this->twitterLists = Model_TwitterList::fetchAll('campaign_id = :cid', array(':cid'=>$this->id));
		return $this->twitterLists;
	}

	function getTwitterSearches() {
		$this->twitterSearches = Model_TwitterSearch::fetchAll('campaign_id = :cid', array(':cid'=>$this->id));
		return $this->twitterSearches;
	}

	function getActiveTwitterLists() {
		$this->activeTwitterLists = Model_TwitterList::fetchAll('campaign_id = :cid AND status = 1',
			array(':cid' => $this->id));
		return $this->activeTwitterLists;
	}

    function getPresences() {
        $this->presences = $this->facebookPages;
        return $this->presences;
    }

	function getActiveTwitterSearches() {
		$this->activeTwitterSearches = Model_TwitterSearch::fetchAll('campaign_id = :cid AND status = 1',
			array(':cid'=>$this->id));
		return $this->activeTwitterSearches;
	}

	function getFacebookPages() {
		$this->facebookPages = Model_FacebookPage::fetchAll('campaign_id = ' . $this->id);
		return $this->facebookPages;
	}

	public function getTwitterToken() {
		return $this->twitterToken = Model_TwitterToken::fetchById($this->token_id);
	}

	private function countStatusThisMonth($oaOnly = false) {
		if ($oaOnly && !$this->analysis_quota) return 0;

		$count = 0;
		foreach ($this->facebookPages as $page) {
			if (!$oaOnly || $page->analysis_quota) $count += $page->posts_this_month;
		}
		foreach ($this->twitterSearches as $search) {
			if (!$oaOnly || $search->analysis_quota) $count += $search->tweets_this_month;
		}
		foreach ($this->twitterLists as $list) {
			if (!$oaOnly || $list->analysis_quota) $count += $list->tweets_this_month;
		}
		return $count;
	}

	public function getStatusCountThisMonth() {
		return $this->countStatusThisMonth(false);
	}

}
