<?php

class Model_TwitterUser extends Model_Base {
	protected static $tableName = 'twitter_users';

	const FRIENDS = 1, FOLLOWERS = 2;

	/**
	 * Fetches the user with the given handle from the database, falling back on the API if not found
	 * @param $handle
	 * @param $token
	 * @return Model_Base|Model_TwitterUser|null
	 */
	public static function fetchByHandle($handle, $token) {
		//try to load from DB
		$user = self::fetchBy('screen_name', $handle);
		if (!$user) {
			$user = self::lookupByHandle($handle, $token);
		}
		return $user;
	}

	/**
	 * Fetches the user with the given handle using the twitter API
	 * @param $handle
	 * @param $token Model_TwitterToken
	 * @return Model_TwitterUser|null
	 */
	public static function lookupByHandle($handle, $token) {
		//fetch from API
		$args = array(
			'screen_name' => $handle
		);
		$result = $token->apiRequest('users/lookup', $args);
		$user = null;
		if ($result && is_array($result)) {
			$data = array_shift($result);
			if (!isset($data->errors)) {
				$userData = array(
					'id' => $data->id_str,
					'name' => $data->name,
					'description' => $data->description,
					'statuses_count' => $data->statuses_count,
					'profile_image_url' => $data->profile_image_url,
					'followers_count' => $data->followers_count,
					'screen_name' => $data->screen_name,
					'url' => $data->url,
					'friends_count' => $data->friends_count
				);
				//check if a user exists with this ID (user may have changed screen name)
				$user = self::fetchById($data->id_str);
				if ($user) {
					//update user
					$user->fromArray($userData);
				} else {
					//create new user
					$user = new self($userData);
				}
			}
		}
		return $user;
	}

	/**
	 * Returns an array of TwitterUsers with the given IDs. Uses local database versions if available,
	 * otherwise fetches them from twitter
	 * @static
	 * @param $ids
	 * @param null $token
	 * @return array
	 */
	public static function fetchById($ids, $token = null) {
		if (!is_array($ids)) return parent::fetchById($ids);
		if (empty($ids)) return array();

		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		$ids = array_filter($ids, 'is_numeric');

		// fetch matching users from DB, that aren't too stale
		$sql = 'SELECT * FROM twitter_users
			WHERE id IN (' . implode(',', $ids) . ')';
		$statement = $db->query($sql);
		$usersFromDb = self::objectify($statement);

		if (count($usersFromDb) == count($ids) || !$token) {
			//all users were in db
			return $usersFromDb;
		} else {

			$foundIds = array();
			foreach ($usersFromDb as $user) {
				$foundIds[] = $user->id;
			}

			$idsToFetch = array_diff($ids, $foundIds);
			$idsFetched = array();
			$length = 100;
			$offset = 0;
			$newUsers = array();
			while ($offset < count($idsToFetch)) {
				$ids = array_slice($idsToFetch, $offset, $length);
				$args = array(
					'user_id' => implode(',', $ids)
				);
				try {
					$userData = $token->apiRequest('users/lookup', $args);
					foreach ($userData as $d) {
						$idsFetched[] = $d->id_str;
						$newUsers[] = array(
							'id' => $d->id_str,
							'name' => $d->name,
							'description' => $d->description,
							'statuses_count' => $d->statuses_count,
							'profile_image_url' => $d->profile_image_url,
							'followers_count' => $d->followers_count,
							'screen_name' => $d->screen_name,
							'url' => $d->url,
							'friends_count' => $d->friends_count,
							'user_last_updated' => gmdate('Y-m-d H:i:s')
						);
					}
				} catch (Exception $ex) {
					// TODO: This can happen if fetching info for one user that does not exist. What should happen in this case?
				}
				$offset += $length;
			}

			//save new users to DB
			self::insertData('twitter_users', $newUsers);

			$usersFromApi = self::fetchById($idsFetched);

			return array_merge($usersFromDb, $usersFromApi);
		}
	}

	private function relatedTypeString($type) {
		if (!in_array($type, array(self::FRIENDS, self::FOLLOWERS))) {
			throw new InvalidArgumentException('Type must be friends or followers');
		}
		return $type == self::FRIENDS ? 'friends' : 'followers';
	}

	/**
	 * @param int $type
	 * @return string Last updated date
	 * @throws InvalidArgumentException
	 */
	public function relatedLastUpdated($type) {
		$typeString = $this->relatedTypeString($type);
		$propertyName = 'last_updated_' . $typeString;
		if (!isset($this->$propertyName)) {
			$statement = $this->_db->prepare('SELECT last_updated FROM twitter_user_relationships WHERE twitter_user_id = :id AND type = :type');
			$statement->execute(array(':id'=>$this->id, ':type'=>$typeString));
			$this->$propertyName = $statement->fetchColumn();
		}
		return $this->$propertyName;
	}

	/**
	 * @param int $type One of Model_TwitterUser::FRIENDS or Model_TwitterUser::FOLLOWERS
	 * @return bool
	 */
	public function relatedUpToDate($type) {
		$lastUpdated = $this->relatedLastUpdated($type);
		$config = Zend_Registry::get('config');
		$cutoffTime = time() - $config->twitter->cache_user_data * 3600 * 24;
		return $lastUpdated && strtotime($lastUpdated) > $cutoffTime;
	}

	/**
	 * Gets the cached friend/follower IDs from the database
	 * @param int $type One of Model_TwitterUser::FRIENDS or Model_TwitterUser::FOLLOWERS
	 * @return array
	 * @throws InvalidArgumentException
	 */
	public function getRelatedIds($type) {
		$typeString = $this->relatedTypeString($type);
		$statement = $this->_db->prepare("SELECT related_ids
			FROM twitter_user_relationships
			WHERE twitter_user_id = :id
			AND type = :type");
		$statement->execute(array(':id'=>$this->id, ':type'=>$typeString));
		$ids = $statement->fetchColumn();
		return $ids ? explode(',', $ids) : array();
	}

	/**
	 * Retrieves the friend/follower IDs from the twitter API.
	 * @param int $type One of Model_TwitterUser::FRIENDS or Model_TwitterUser::FOLLOWERS
	 * @param Model_TwitterToken $token
	 * @throws Exception_TwitterApi
	 * @return array
	 */
	public function fetchRelatedIds($type, $token) {
		$typeString = $this->relatedTypeString($type);

		// ensure a row exists
		$args = array(':id'=>$this->id, ':type'=>$typeString);
		$stmt = $this->_db->prepare('INSERT IGNORE INTO twitter_user_relationships (twitter_user_id, type) VALUES (:id, :type)');
		$stmt->execute($args);

		// get the ID of the relationship row
		$stmt = $this->_db->prepare('SELECT id FROM twitter_user_relationships WHERE twitter_user_id = :id AND type = :type');
		$stmt->execute(array(':id'=>$this->id, ':type'=>$typeString));
		$relationshipId = $stmt->fetchColumn();

		// get the partial update (if any)
		$stmt = $this->_db->prepare("SELECT partial_update
			FROM twitter_user_relationships
			WHERE id = :id");
		$stmt->execute(array(':id'=>$relationshipId));
		$partialUpdate = $stmt->fetchColumn();

		// initialise the ids and the cursor
		$ids = array();
		$cursor = -1;
		if ($partialUpdate) {
			$partialUpdate = json_decode($partialUpdate);

			// only use the partial data if it is sufficiently recent
			$config = Zend_Registry::get('config');
			$cutoffTime = time() - $config->twitter->cache_user_data * 3600 * 24;
			if (strtotime($partialUpdate->last_request) > $cutoffTime) {
				$ids = $partialUpdate->ids;
				$cursor = $partialUpdate->cursor;
			}
		}

		$path = $typeString . '/ids';
		try {
			// loop through IDs until we reach the end
			while ($cursor != 0) {
				$args = array(
					'user_id' => $this->id,
					'cursor' => $cursor,
					'stringify_ids' => true
				);
				try {
					$result = $token->apiRequest($path, $args);
					$ids = array_merge($ids, $result->ids);
					$cursor = $result->next_cursor_str;
				} catch (Exception_TwitterApi $ex) {
					//protected user so stop now
					if ($ex->getCode() == Model_TwitterStatusCodes::UNAUTHORIZED) {
						$cursor = 0;
					} else {
						throw $ex;
					}
				}
			}
			// store the IDs in the database
			$stmt = $this->_db->prepare('UPDATE twitter_user_relationships
				SET last_updated = :last_updated,
				related_ids = :related_ids,
				partial_update = NULL
				WHERE id = :id');
			$stmt->execute(array(':last_updated'=>date('Y-m-d H:i:s'), ':related_ids'=>implode(',',$ids), ':id'=>$relationshipId));
			return $ids;
		} catch (Exception_TwitterApi $e) {
			if ($e->getCode() == Model_TwitterStatusCodes::TOO_MANY_REQUESTS) {
				// store the partial result in the database
				$partialUpdate = array(
					'ids'=>$ids,
					'cursor'=>$cursor,
					'last_request'=>date('Y-m-d H:i:s')
				);
				$stmt = $this->_db->prepare('UPDATE twitter_user_relationships SET partial_update = :partial WHERE id = :id');
				$stmt->execute(array(':partial'=>json_encode($partialUpdate), ':id'=>$relationshipId));
			}
			throw $e;
		}
	}
	
	public function updateFromApi($token) {
		$userData = $token->apiRequest('users/lookup', array('user_id'=>$this->id));
		if (count($userData)) {
			$d = $userData[0];
			$this->fromArray(array(
				'id' => $d->id_str,
				'name' => $d->name,
				'description' => $d->description,
				'statuses_count' => $d->statuses_count,
				'profile_image_url' => $d->profile_image_url,
				'followers_count' => $d->followers_count,
				'screen_name' => $d->screen_name,
				'url' => $d->url,
				'friends_count' => $d->friends_count,
				'user_last_updated' => gmdate('Y-m-d H:i:s')
			));
		}
	}

	/**
	 * Deletes friend/follower IDs before deleting this user
	 */
	public function delete() {
		$args = array(':id'=>$this->id);
		$this->_db->prepare('DELETE FROM twitter_user_relationships WHERE id = :id')->execute($args);
		parent::delete();
	}


}
