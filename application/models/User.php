<?php

class Model_User extends Model_Base implements Zend_Auth_Adapter_Interface {
	protected $_tableName = 'users', $_sortColumn = 'name', $_authName = '', $_authPassword = '';

	const PASSWORD_SALT = 'Humpty dumpty sat on a wall';

	public static $userLevels = array(
		'1'=>'User',
		'5'=>'Manager',
		'10'=>'Admin'
	);

	protected static $permissions = array();

	public static function getPermissions($userLevel) {
		if (!array_key_exists($userLevel, self::$permissions)) {
			$db = Zend_Registry::get('db');
			$statement = $db->prepare('SELECT permission FROM user_permissions WHERE user_level = ?');
			$statement->execute(array($userLevel));
			self::$permissions[$userLevel] = $statement->fetchAll(PDO::FETCH_COLUMN);
		}
		return self::$permissions[$userLevel];
	}

	function setAuthPassword($p) {
		$this->_authPassword = $p;
	}
	
	function setAuthName($p) {
		$this->_authName = $p;
	}
	
	function fromArray($data)
	{
		parent::fromArray($data);
		// only set the password hash if a password was provided. Logic for ensuring
		// new users get an initial password is dealt with in the controller
		if (!empty($data['password'])) {
			$this->password_hash = sha1(self::PASSWORD_SALT.$data['password']);
		}
	}
	
	// required by Zend_Auth_Adapter_Interface. Used by Zend for authenticating users
	function authenticate()
	{
		$statement = $this->_db->prepare('SELECT id FROM users WHERE (name = :name OR email = :name) AND password_hash = :hash');
		$statement->execute(array(':name'=>$this->_authName, ':hash'=>sha1(self::PASSWORD_SALT.$this->_authPassword)));
		$id = $statement->fetchColumn();
		$code = ($id ? Zend_Auth_Result::SUCCESS : Zend_Auth_Result::FAILURE);
		return new Zend_Auth_Result($code, $id);
	}

	public function getTwitterToken() {
		return $this->twitterToken = Model_TwitterToken::fetchById($this->token_id);
	}

	/**
	 * Returns an encoded version of the username
	 */
	function getSafeName() {
		return htmlspecialchars($this->name);
	}

	/**
	 * Returns true if $action is blank, or if the user_level has been assigned the action
	 * @param $action
	 * @return bool
	 */
	function canPerform($action) {
		return !$action || in_array($action, Model_User::getPermissions($this->user_level));
	}

	function canManageCampaigns() {
		return !$this->isAdmin;
	}

	function getIsAdmin() {
		$levels = array_flip(self::$userLevels);
		$adminLevel = (int)$levels['Admin'];
		return $this->user_level >= $adminLevel;
	}
	
	function getCampaigns() {
		return $this->campaigns = Model_Campaign::fetchAll();
	}
	
	function getCampaignIds() {
		$this->campaignIds = array();
		foreach ($this->campaigns as $c) {
			$this->campaignIds[] = $c->id;
		}
		return $this->campaignIds;
	}
	
	function assignCampaign($id) {
		$this->updateCampaigns('INSERT INTO user_campaigns (user_id, campaign_id) VALUES (:user_id, :campaign_id)', $id);
	}
	
	function unassignCampaign($id) {
		$this->updateCampaigns('DELETE FROM user_campaigns WHERE user_id = :user_id AND campaign_id = :campaign_id', $id);
	}
	
	private function updateCampaigns($sql, $campaignId) {
		$statement = $this->_db->prepare($sql);
		try {
			$statement->execute(array(':user_id'=>$this->id, ':campaign_id'=>$campaignId));
			unset($this->campaigns);
			unset($this->campaignIds);
		} catch (Exception $ex) { }
	}

	public function delete() {
		$this->_db->prepare('DELETE FROM user_campaigns WHERE user_id = ?')->execute(array($this->id));
		parent::delete();
	}

	/**
	 * @return Model_JobSubscription[]
	 */
	public function getJobSubscriptions() {
		$stmt = $this->_db->prepare('SELECT * FROM job_subscriptions WHERE user_id = :id');
		$stmt->execute(array(':id' => $this->id));
		return Model_JobSubscription::objectify($stmt);
	}
}
