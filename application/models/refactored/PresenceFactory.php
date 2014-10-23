<?php

abstract class NewModel_PresenceFactory
{

	protected static $db;

	protected static $defaultQueryOptions = array(
		'orderColumn'		=> '`p`.`handle`',
		'orderDirection'	=> 'ASC',
		'offset'				=> 0,
		'limit'				=> 0
	);

	public static function getPresences(array $queryOptions = array())
	{
		$queryOptions = array_merge(self::$defaultQueryOptions, $queryOptions);
		$sql = "SELECT * FROM `presences` AS `p`";
		if (strlen($queryOptions['orderColumn'])) {
			$sql .= " ORDER BY ".$queryOptions['orderColumn'].' '.$queryOptions['orderDirection'];
		}
		if ($queryOptions['limit'] > 0) {
			$sql .= " LIMIT ".$queryOptions['offset'].','.$queryOptions['limit'];
		}

		$stmt = self::$db->prepare($sql);
		$stmt->execute();
		$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if ($results == false) return array();

		$presences = array_map('self::instantiatePresence', $results);

		return array_filter($presences);
	}

    /**
     * @param $id
     * @return NewModel_Presence|null
     */
    public static function getPresenceById($id)
	{
		$stmt = static::$db->prepare("SELECT * FROM `presences` WHERE `id` = :id");
		$stmt->execute(array(':id' => $id));
		$internals = $stmt->fetch(PDO::FETCH_ASSOC);

		return self::instantiatePresence($internals);
	}

	public static function getPresenceByHandle($handle, NewModel_PresenceType $type)
	{
		$stmt = static::$db->prepare("SELECT * FROM `presences` WHERE `handle` = :handle AND `type` = :t");
		$stmt->execute(array(':handle' => $handle, ':t' => $type));
		$internals = $stmt->fetch(PDO::FETCH_ASSOC);

		return self::instantiatePresence($internals);
	}

	public static function getPresencesByType(NewModel_PresenceType $type, array $queryOptions = array())
	{
		$queryOptions = array_merge(static::$defaultQueryOptions, $queryOptions);
		$sql = "SELECT * FROM `presences` AS `p` WHERE `type` = :type";
		if (strlen($queryOptions['orderColumn'])) {
			$sql .= " ORDER BY ".$queryOptions['orderColumn'].' '.$queryOptions['orderDirection'];
		}
		if ($queryOptions['limit'] > 0) {
			$sql .= " LIMIT ".$queryOptions['offset'].','.$queryOptions['limit'];
		}

		$stmt = static::$db->prepare($sql);
		$stmt->execute(array(':type' => $type));
		$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if($results == false) return array();

		$presences = array_map('self::instantiatePresence', $results);

		return $presences;
	}

	public static function getPresencesById(array $ids, array $queryOptions = array())
	{
		$queryOptions = array_merge(static::$defaultQueryOptions, $queryOptions);
		$inQuery = implode(',', array_fill(0, count($ids), '?'));
		$sql = "SELECT * FROM `presences` AS `p` WHERE `id` IN ({$inQuery})";
		if (strlen($queryOptions['orderColumn'])) {
			$sql .= " ORDER BY ".$queryOptions['orderColumn'].' '.$queryOptions['orderDirection'];
		}
		if ($queryOptions['limit'] > 0) {
			$sql .= " LIMIT ".$queryOptions['offset'].','.$queryOptions['limit'];
		}

		$stmt = static::$db->prepare($sql);
		$stmt->execute($ids);
		$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if($results == false) return array();

		$presences = array_map('self::instantiatePresence', $results);

		return array_filter($presences);
	}

	public static function getPresencesByCampaign($campaign, array $queryOptions = array())
	{
		$queryOptions = array_merge(static::$defaultQueryOptions, $queryOptions);
		$sql = "SELECT `p`.* FROM `campaign_presences` AS `cp` LEFT JOIN `presences` AS `p` ON (`cp`.`presence_id` = `p`.`id`) WHERE `cp`.`campaign_id` = :cid";
		if (strlen($queryOptions['orderColumn'])) {
			$sql .= " ORDER BY ".$queryOptions['orderColumn'].' '.$queryOptions['orderDirection'];
		}
		if ($queryOptions['limit'] > 0) {
			$sql .= " LIMIT ".$queryOptions['offset'].','.$queryOptions['limit'];
		}

		$stmt = static::$db->prepare($sql);
		$stmt->execute(array(":cid" => $campaign));
		$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if($results == false) return array();

		$presences = array_map('self::instantiatePresence', $results);

		return array_filter($presences);
	}

	public static function createNewPresence(NewModel_PresenceType $type, $handle, $signed_off, $branding)
	{
		$signed_off = !!$signed_off;
		$branding = !!$branding;

		$provider = $type->getProvider(static::$db);

		$args = $provider->updateNew($handle);

		if (!$args) {
			return false;
		} else {
			//insert presence
			$stmt = static::$db->prepare("
				INSERT INTO `presences`
				(`type`, `handle`, `uid`, `image_url`, `name`, `page_url`, `popularity`, `klout_id`, `klout_score`, `facebook_engagement`, `last_updated`, `sign_off`, `branding`)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
			$args[] = $signed_off ? 1 : 0;
			$args[] = $branding ? 1 : 0;
			return $stmt->execute(array_values($args));
		}
	}

	public static function setDatabase(PDO $db)
	{
		static::$db = $db;
	}


	protected static function instantiatePresence($internals)
	{
		if($internals != false) {
			$type = new NewModel_PresenceType($internals['type']);
			$provider = $type->getProvider(static::$db);
			$metrics = $type->getMetrics();
			$badges = $type->getBadges();
			return new NewModel_Presence(static::$db, $internals, $provider, $metrics, $badges);
		} else {
			return null;
		}
	}
}