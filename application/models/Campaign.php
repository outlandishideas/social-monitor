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

	function getUsers() {
		$statement = $this->_db->prepare('SELECT u.* FROM users AS u INNER JOIN user_campaigns AS uc ON u.id = uc.user_id WHERE uc.campaign_id = :campaign_id');
		$statement->execute(array(':campaign_id'=>$this->id));
		$this->users = Model_User::objectify($statement->fetchAll());
		return $this->users;
	}
	
	function getUserIds() {
		$this->userIds = array();
		foreach ($this->users as $u) {
			$this->userIds[] = $u->id;
		}
		return $this->userIds;
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

	public function getOpenAmplifyCountThisMonth() {
		return $this->countStatusThisMonth(true);
	}

	/**
	 * Generates an array of months (grouped by year) for which there is data, with the total API calls for each month.
	 * Format is
	 * {
	 *   2012 => {
	 *     04 => 1234,
	 *     05 => 1001,
	 *     06 => 1321
	 *   }
	 * }
	 * @return array
	 */
	public function getAvailableApiDates() {
		$statement = $this->_db->prepare('SELECT name, value FROM options WHERE name LIKE ?');
		$statement->execute(array("open_amplify%campaign_{$this->id}"));
		$data = $statement->fetchAll(PDO::FETCH_ASSOC);
		$dates = array();
		foreach ($data as $row) {
			if (preg_match('/open_amplify_(\d{4})-(\d{2})-\d{2}_campaign/', $row['name'], $matches)) {
				$year = $matches[1];
				$month = $matches[2];
				if (!array_key_exists($year, $dates)) {
					$dates[$year] = array();
				}
				if (!array_key_exists($month, $dates[$year])) {
					$dates[$year][$month] = 0;
				}
				$dates[$year][$month] += $row['value'];
			}
		}
		ksort($dates);
		foreach ($dates as $year=>$months) {
			ksort($dates[$year]);
		}
		return $dates;
	}

	/**
	 * Gets monthly OpenAmplify usage stats for the campaign
	 */
	public function getApiStats($year = null, $month = null) {
		if (!$year) {
			$year = date('Y');
		}
		if (!$month) {
			$month = date('m');
		}
		if (strlen($month) < 2) { $month = '0' . $month; }
		$statement = $this->_db->prepare('SELECT name, value FROM options WHERE name LIKE ?');
		$statement->execute(array("open_amplify_{$year}-{$month}%_campaign_{$this->id}"));
		$data = $statement->fetchAll(PDO::FETCH_ASSOC);

		$stats = array();
		foreach ($data as $row) {
			if (preg_match('/open_amplify_(' . $year . '-' . $month . '-' . '\d{2})_campaign/', $row['name'], $matches)) {
				$stats[$matches[1]] = $row['value'];
			}
		}
		ksort($stats);
		return $stats;
	}

	function assignUser($id) {
		$this->updateUsers('INSERT INTO user_campaigns (user_id, campaign_id) VALUES (:user_id, :campaign_id)', $id);
	}
	
	function unassignUser($id) {
		$this->updateUsers('DELETE FROM user_campaigns WHERE user_id = :user_id AND campaign_id = :campaign_id', $id);
	}
	
	private function updateUsers($sql, $userId) {
		$statement = $this->_db->prepare($sql);
		try {
			$statement->execute(array(':user_id'=>$userId, ':campaign_id'=>$this->id));
			unset($this->users);
			unset($this->userIds);
		} catch (Exception $ex) { }
	}
}
