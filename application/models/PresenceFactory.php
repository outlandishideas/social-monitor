<?php

use Outlandish\SocialMonitor\Database\Database;
use Outlandish\SocialMonitor\PresenceType\PresenceType;

abstract class Model_PresenceFactory
{
    const TABLE_PRESENCES = 'presences';
    const TABLE_CAMPAIGN_PRESENCES = 'campaign_presences';

    /** @var Database */
	protected static $db;

	protected static function appendSqlOptions($sql, $queryOptions)
	{
		$defaultOptions = array(
			'orderColumn'		=> '`p`.`handle`',
			'orderDirection'	=> 'ASC',
			'offset'			=> 0,
			'limit'				=> 0
		);
		$queryOptions = array_merge($defaultOptions, $queryOptions);
		if (strlen($queryOptions['orderColumn'])) {
			$sql .= " ORDER BY ".$queryOptions['orderColumn'].' '.$queryOptions['orderDirection'];
		}
		if ($queryOptions['limit'] > 0) {
			$sql .= " LIMIT ".$queryOptions['offset'].','.$queryOptions['limit'];
		}
		return $sql;
	}

	public static function getPresences(array $queryOptions = array())
	{
		$sql = "SELECT * FROM `" . self::TABLE_PRESENCES . "` AS `p`";
		$sql = self::appendSqlOptions($sql, $queryOptions);

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

	public static function getPresenceByHandle($handle, PresenceType $type)
	{
        $presences = self::fetchPresences("SELECT * FROM `" . self::TABLE_PRESENCES . "` WHERE `handle` = :handle AND `type` = :t", array(':handle' => $handle, ':t' => $type));
        return $presences ? $presences[0] : null;
	}

    public static function getPresencesByType(PresenceType $type, array $queryOptions = array())
	{
		$sql = "SELECT * FROM `" . self::TABLE_PRESENCES . "` AS `p` WHERE `type` = :type";
		$sql = self::appendSqlOptions($sql, $queryOptions);

        return self::fetchPresences($sql, array(':type'=>$type));
	}

	public static function getPresencesById(array $ids, array $queryOptions = array())
	{
		$inQuery = implode(',', array_fill(0, count($ids), '?'));
		$sql = "SELECT * FROM `" . self::TABLE_PRESENCES . "` AS `p` WHERE `id` IN ({$inQuery})";
		$sql = self::appendSqlOptions($sql, $queryOptions);

        return self::fetchPresences($sql, $ids);
	}

	public static function getPresencesByCampaigns($campaignIds, array $queryOptions = array())
	{
		$presencesTable = self::TABLE_PRESENCES;
		$campaignPresencesTable = self::TABLE_CAMPAIGN_PRESENCES;
		$placeholders = implode(', ', array_fill(0, count($campaignIds), '?'));
		$sql = "SELECT p.* 
			FROM $campaignPresencesTable AS cp 
			INNER JOIN $presencesTable AS p ON (cp.presence_id = p.id) 
			WHERE `cp`.`campaign_id` IN ($placeholders)";
		$sql = self::appendSqlOptions($sql, $queryOptions);

        return self::fetchPresences($sql, $campaignIds);
	}

	public static function createNewPresence(PresenceType $type, $handle, $signed_off, $branding)
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
            $presence->sign_off = (int)!!$signed_off;
            $presence->branding = (int)!!$branding;
            $presence->save();
        } catch (Exception $ex) {
            $stmt = self::$db->prepare('DELETE FROM ' . self::TABLE_PRESENCES . ' WHERE ID = ?');
            $stmt->execute(array($presence->id));
            throw $ex;
        }

        return $presence;
	}

	public static function setDatabase(Database $db)
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
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!$results) {
            return array();
        }

        $presences = array();
		foreach ($results as $internals) {
			$presence = self::instantiatePresence($internals);
			if ($presence) {
				$presences[] = $presence;
			}
		}

        return $presences;
    }

	protected static function instantiatePresence($internals)
	{
		if($internals) {
			$type = PresenceType::get($internals['type']);
			$provider = $type->getProvider();
			$metrics = $type->getMetrics();
			return new Model_Presence(static::$db, $internals, $provider, $metrics);
		} else {
			return null;
		}
	}
}