<?php
use Carbon\Carbon;
use Outlandish\SocialMonitor\Models\AccessToken;
use Outlandish\SocialMonitor\PresenceType\PresenceType;

/**
 * @property string|null email
 * @property string|null name
 * @property int|null id
 * @property string confirm_email_key
 */
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
			$db = BaseController::db();
			$statement = $db->prepare('SELECT permission FROM user_permissions WHERE user_level = ?');
			$statement->execute(array($userLevel));
			self::$permissions[$userLevel] = $statement->fetchAll(\PDO::FETCH_COLUMN);
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
		$statement = $this->_db->prepare('SELECT id FROM users WHERE (name = :name OR email = :name) AND password_hash = :hash AND confirm_email_key IS NULL');
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
	 * @param $levelName
	 * @param $controller
	 * @param $action
	 * @param $id
	 * @return bool
	 */
	function canPerform($levelName, $controller, $action, $id) {
		if ($this->isManager || !$levelName) {
			return true;
		}
		$levelName = strtolower($levelName);
		$levels = self::$userLevels;
		foreach ($levels as $l=>$label) {
			if (strtolower($label) == $levelName && $this->user_level >= $l) {
				// only check specific entities if an id given and action not view
				if (!$id || $action === 'view') {
					return true;
				}
				$entities = $this->getAccessEntities();
				/** @var Model_Campaign[] $campaigns */
				$campaigns = array();
				foreach ($entities as $e) {
					if ($e->controller == $controller && $e->entity_id == $id) {
						return true;
					}
					if ($e->model instanceof Model_Campaign) {
						$campaigns[] = $e->model;
					}
				}
				if ($controller == 'presence') {
					foreach ($campaigns as $c) {
						if (in_array($id, $c->getPresenceIds())) {
							return true;
						}
					}
				}
			}
		}
		return false;
	}

	function getIsManager() {
		return $this->user_level >= self::USER_LEVEL_MANAGER;
	}

	function getIsAdmin() {
		return $this->user_level >= self::USER_LEVEL_ADMIN;
	}

	function getAccessEntities() {
		if (!isset($this->entities)) {
			$stmt = $this->_db->prepare('SELECT * FROM user_access WHERE user_id = :id ORDER BY entity_type ASC, id ASC');
			$stmt->execute(array(':id'=>$this->id));
			$entities = $stmt->fetchAll(\PDO::FETCH_OBJ);
			foreach ($entities as $i=>$e) {
				$entity = null;
				$e->controller = $e->entity_type;
				switch ($e->entity_type) {
					case 'twitter':
					case 'facebook':
						$e->controller = 'presence';
						$entity = Model_PresenceFactory::getPresenceById($e->entity_id);
						if ($entity) {
							$e->icon = 'icon-'.$entity->type.'-sign';
							$e->title = $entity->getLabel();
							$e->text = ($entity->isForTwitter() ? '@' : '') . $entity->handle;
						}
						break;
					case 'country':
						$entity = Model_Country::fetchById($e->entity_id);
						if ($entity) {
							$e->icon = Model_Country::ICON_TYPE;
							$e->title = '';
							$e->text = $entity->display_name;
						}
						break;
					case 'group':
						$entity = Model_Group::fetchById($e->entity_id);
						if ($entity) {
							$e->icon = Model_Group::ICON_TYPE;
							$e->title = '';
							$e->text = $entity->display_name;
						}
						break;
				}

				if ($entity) {
					$e->model = $entity;
				} else {
					unset($entities[$i]);
				}
			}
			$this->entities = $entities;
		}
		return $this->entities;
	}

	function assignAccess($toAssign) {
		$rows = array();
		foreach ($toAssign as $type=>$ids) {
			foreach ($ids as $id) {
				$rows[] = array(
					'user_id'=>$this->id,
					'entity_type'=>$type,
					'entity_id'=>$id
				);
			}
		}
		$stmt = $this->_db->prepare('DELETE FROM user_access WHERE user_id = :id');
		$stmt->execute(array(':id'=>$this->id));
		$this->insertData('user_access', $rows);
	}

	public function getAccessToken(PresenceType $type)
	{
		$stmt = $this->_db->prepare('SELECT token, expires FROM access_tokens WHERE user_id = :user_id AND `presence_type` = :presence_type');
		$result = $stmt->execute(array(':user_id'=>$this->id, ':presence_type' => $type->getValue()));

		if ($result) {
			list($token, $expires) = $stmt->fetch(\PDO::FETCH_NUM);
			return new AccessToken($token, $expires);
		} else {
			return null;
		}
	}

    public function deleteAccessToken(PresenceType $type)
    {
        $stmt = $this->_db->prepare('DELETE FROM access_tokens WHERE user_id = :user_id AND presence_type = :presence_type');
        $stmt->execute([':user_id' => $this->id, ':presence_type' => $type->getValue()]);
    }

	public function saveAccessToken(PresenceType $type, $token, Carbon $expires)
	{
		$stmt = $this->_db->prepare('
							INSERT INTO access_tokens (`user_id`, `presence_type`, `token`, `expires`)
							VALUES(:user_id, :presence_type, :token, :expires)
							ON DUPLICATE KEY UPDATE
							token = VALUES(token),
							expires = VALUES(expires)');
		return $stmt->execute([
			':user_id' => $this->id,
			':presence_type' => $type->getValue(),
			':token' => $token,
			':expires' => $expires->timestamp
		]);
	}

	public function hasAccessTokensNeedRefreshing()
	{
		foreach (PresenceType::getAll() as $type) {
			if ($type->getRequiresAccessToken()) {
				$accessToken = $this->getAccessToken($type);
				if (!$accessToken || $accessToken->expiresSoon()) {
					return true;
				}
			}
		}

		return false;
	}
}
