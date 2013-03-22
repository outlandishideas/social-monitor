<?php

class Model_CampaignNetwork extends Model_Base {
	protected $_tableName = 'campaign_networks', $_sortColumn = 'id';

	public static $typeLabels = array(
		Model_TwitterUser::FRIENDS => 'Following',
		Model_TwitterUser::FOLLOWERS => 'Followers'
	);

	public function delete() {
		$this->_db->prepare('DELETE FROM campaign_network_users WHERE network_id = ?')->execute(array($this->id));
		parent::delete();
	}

	public function getTypeLabel() {
		return self::$typeLabels[$this->type];
	}

	/**
	 * @return Model_TwitterUser[]
	 */
	public function getTwitterUsers() {
		$statement = $this->_db->prepare('SELECT tu.*
			FROM twitter_users AS tu
			INNER JOIN campaign_network_users AS cnu ON tu.id = cnu.twitter_user_id
			WHERE cnu.network_id = :campaign_id');
		$statement->execute(array(':campaign_id'=>$this->id));
		$data = $statement->fetchAll(PDO::FETCH_ASSOC);
		return $this->twitterUsers = Model_TwitterUser::objectify($data);
	}

	/**
	 * @param Model_TwitterUser $user
	 */
	public function addUser($user) {
		$toInsert = array(
			'network_id' => $this->id,
			'twitter_user_id' => $user->id
		);
		self::insertData('campaign_network_users', array($toInsert));
	}

	/**
	 * @param Model_TwitterUser|int $user User object or user ID to remove from network
	 * @return bool success
	 */
	public function removeUser($user) {
		$userId = is_object($user) ? $user->id : $user;
		$statement = $this->_db->prepare('DELETE FROM campaign_network_users WHERE network_id = :network_id AND twitter_user_id = :twitter_user_id');
		$statement->execute(array(':network_id'=>$this->id, ':twitter_user_id'=>$userId));
		return $statement->rowCount() == 1;
	}
}