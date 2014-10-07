<?php

abstract class Model_PresenceFactory
{

	protected static $db;

	public static function getPresenceById($id)
	{
		$stmt = self::$db->prepare("SELECT * FROM `presences` WHERE `id` = :id");
		$stmt->execute(array(':id' => $id));
		$internals = $stmt->fetch(PDO::FETCH_ASSOC);
		$type = new Model_PresenceType($internals['type']);
		$provider = $type->getProvider(self::$db);
		return new Model_Presence($internals, $provider);
	}

	public static function getPresenceByHandle($handle, Model_PresenceType $type)
	{
		$stmt = self::$db->prepare("SELECT * FROM `presences` WHERE `handle` = :handle AND `type` = :t");
		$stmt->execute(array(':handle' => $handle, ':t' => $type));
		$internals = $stmt->fetch(PDO::FETCH_ASSOC);
		$type = new Model_PresenceType($internals['type']);
		$provider = $type->getProvider(self::$db);
		return new Model_Presence($internals, $provider);
	}

	public static function getPresencesByType(Model_PresenceType $type)
	{
		$stmt = self::$db->prepare("SELECT * FROM `presences` WHERE `type` = :type");
		$stmt->execute(array(':type' => $type));
		$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$presences = array_map(function($internals){
			$type = new Model_PresenceType($internals['type']);
			$provider = $type->getProvider(self::$db);
			return new Model_Presence($internals, $provider);
		}, $results);

		return $presences;
	}

	public static function getPresencesById(array $ids)
	{
		$inQuery = implode(',', array_fill(0, count($ids), '?'));
		$stmt = self::$db->prepare("SELECT * FROM `presences` WHERE `id` IN ({$inQuery})");
		$stmt->execute($ids);
		$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$presences = array_map(function($internals){
			$type = new Model_PresenceType($internals['type']);
			$provider = $type->getProvider(self::$db);
			return new Model_Presence($internals, $provider);
		}, $results);

		return $presences;
	}

	public static function getPresencesByCampaign($campaign)
	{
		$stmt = self::$db->prepare("SELECT presence_id FROM `campaign_presences` WHERE `campaign_id` = :cid");
		$stmt->execute(array(":cid" => $campaign));
		$presence_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
		return self::getPresencesById($presence_ids);
	}

	public static function createNewPresence(Model_PresenceType $type, $handle, $signed_off, $branding)
	{
		$signed_off = !!$signed_off;
		$branding = !!$branding;

		$provider = $type->getProvider(self::$db);

		$args = $provider->testHandle($handle);

		if (false === $args) {
			return false;
		} else {
			//insert presence
			$stmt = self::$db->prepare("INSERT INTO `presences` (`type`, `handle`, `uid`, `image_url`, `name`, `page_url`, `popularity`, `sign_off`, `branding`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
			$args[] = $signed_off ? 1 : 0;
			$args[] = $branding ? 1 : 0;
			return $stmt->execute($args);
		}
	}

	public static function setDatabase(PDO $db)
	{
		self::$db = $db;
	}
}