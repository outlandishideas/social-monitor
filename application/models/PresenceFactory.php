<?php

abstract class Model_PresenceFactory
{
    const TABLE_PRESENCES = 'presences';
    const TABLE_CAMPAIGN_PRESENCES = 'campaign_presences';

    /** @var PDO */
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
		$sql = "SELECT * FROM `" . self::TABLE_PRESENCES . "` AS `p`";
		if (strlen($queryOptions['orderColumn'])) {
			$sql .= " ORDER BY ".$queryOptions['orderColumn'].' '.$queryOptions['orderDirection'];
		}
		if ($queryOptions['limit'] > 0) {
			$sql .= " LIMIT ".$queryOptions['offset'].','.$queryOptions['limit'];
		}

        return self::fetchPresences($sql);
	}

    /**
     * @param $id
     * @return Model_Presence|null
     */
    public static function getPresenceById($id)
	{
        $presences = self::fetchPresences("SELECT * FROM `" . self::TABLE_PRESENCES . "` WHERE `id` = :id", array(':id'=>$id));
        return $presences ? $presences[0] : null;
	}

	public static function getPresenceByHandle($handle, Enum_PresenceType $type)
	{
        $presences = self::fetchPresences("SELECT * FROM `" . self::TABLE_PRESENCES . "` WHERE `handle` = :handle AND `type` = :t", array(':handle' => $handle, ':t' => $type));
        return $presences ? $presences[0] : null;
	}

    public static function getPresencesByType(Enum_PresenceType $type, array $queryOptions = array())
	{
		$queryOptions = array_merge(static::$defaultQueryOptions, $queryOptions);
		$sql = "SELECT * FROM `" . self::TABLE_PRESENCES . "` AS `p` WHERE `type` = :type";
		if (strlen($queryOptions['orderColumn'])) {
			$sql .= " ORDER BY ".$queryOptions['orderColumn'].' '.$queryOptions['orderDirection'];
		}
		if ($queryOptions['limit'] > 0) {
			$sql .= " LIMIT ".$queryOptions['offset'].','.$queryOptions['limit'];
		}

        return self::fetchPresences($sql, array(':type'=>$type));
	}

	public static function getPresencesById(array $ids, array $queryOptions = array())
	{
		$queryOptions = array_merge(static::$defaultQueryOptions, $queryOptions);
		$inQuery = implode(',', array_fill(0, count($ids), '?'));
		$sql = "SELECT * FROM `" . self::TABLE_PRESENCES . "` AS `p` WHERE `id` IN ({$inQuery})";
		if (strlen($queryOptions['orderColumn'])) {
			$sql .= " ORDER BY ".$queryOptions['orderColumn'].' '.$queryOptions['orderDirection'];
		}
		if ($queryOptions['limit'] > 0) {
			$sql .= " LIMIT ".$queryOptions['offset'].','.$queryOptions['limit'];
		}

        return self::fetchPresences($sql, $ids);
	}

	public static function getPresencesByCampaign($campaign, array $queryOptions = array())
	{
		$queryOptions = array_merge(static::$defaultQueryOptions, $queryOptions);
		$sql = "SELECT `p`.* FROM `" . self::TABLE_CAMPAIGN_PRESENCES . "` AS `cp` LEFT JOIN `" . self::TABLE_PRESENCES . "` AS `p` ON (`cp`.`presence_id` = `p`.`id`) WHERE `cp`.`campaign_id` = :cid";
		if (strlen($queryOptions['orderColumn'])) {
			$sql .= " ORDER BY ".$queryOptions['orderColumn'].' '.$queryOptions['orderDirection'];
		}
		if ($queryOptions['limit'] > 0) {
			$sql .= " LIMIT ".$queryOptions['offset'].','.$queryOptions['limit'];
		}

        return self::fetchPresences($sql, array(":cid" => $campaign));
	}

	public static function createNewPresence(Enum_PresenceType $type, $handle, $signed_off, $branding)
	{
        // create a new presence
        $stmt = static::$db->prepare("
				INSERT INTO `" . self::TABLE_PRESENCES . "`
				(`type`, `handle`, `uid`, `image_url`, `name`, `page_url`, `popularity`, `sign_off`, `branding`)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(array($type->getValue(), $handle, '', '', '', '', 0, 0, 0));
        $presence = Model_PresenceFactory::getPresenceByHandle($handle, $type);

        try {
            $presence->update();
            $presence->sign_off = !!$signed_off;
            $presence->branding = !!$branding;
            $presence->save();
        } catch (Exception $ex) {
            $stmt = self::$db->prepare('DELETE FROM ' . self::TABLE_PRESENCES . ' WHERE ID = ?');
            $stmt->execute(array($presence->id));
            throw $ex;
        }

        return $presence;
	}

	public static function setDatabase(PDO $db)
	{
		static::$db = $db;
	}

    /**
     * @param $sql
     * @param array $args
     * @return Model_Presence[]
     */
    protected static function fetchPresences($sql, $args = array()) {
        $stmt = self::$db->prepare($sql);
        $stmt->execute($args);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$results) {
            return array();
        }

        $presences = array_map('self::instantiatePresence', $results);

        return array_filter($presences);
    }

	protected static function instantiatePresence($internals)
	{
		if($internals) {
			$type = Enum_PresenceType::get($internals['type']);
			$provider = $type->getProvider(static::$db);
			$metrics = $type->getMetrics();
			$badges = $type->getBadges();
			return new Model_Presence(static::$db, $internals, $provider, $metrics, $badges);
		} else {
			return null;
		}
	}
}