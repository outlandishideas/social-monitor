<?php

class Model_User extends Model_Base implements Zend_Auth_Adapter_Interface {
	protected static $tableName = 'users';
	protected static $sortColumn = 'name';

	protected $_authName = '', $_authPassword = '';

	const PASSWORD_SALT = 'Humpty dumpty sat on a wall';

	const USER_LEVEL_USER = 1;
	const USER_LEVEL_MANAGER = 5;
	const USER_LEVEL_ADMIN = 10;

	public static $userLevels = array(
		self::USER_LEVEL_USER=>'User',
		self::USER_LEVEL_MANAGER=>'Manager',
		self::USER_LEVEL_ADMIN=>'Admin'
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
		//todo: reinstate this when all permissions have been ironed out
		return true;
//		return !$action || in_array($action, Model_User::getPermissions($this->user_level));
	}

	function getIsManager() {
		return $this->user_level >= self::USER_LEVEL_MANAGER;
	}

	function getIsAdmin() {
		return $this->user_level >= self::USER_LEVEL_ADMIN;
	}

	function getAccessEntities() {
		$stmt = $this->_db->prepare('SELECT * FROM user_access WHERE user_id = :id');
		$stmt->execute(array(':id'=>$this->id));
		return $stmt->fetchAll(PDO::FETCH_OBJ);
	}
}
