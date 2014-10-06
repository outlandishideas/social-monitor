<?php

abstract class Model_PresenceFactory
{

	protected static $db;

	public static function getPresenceById($id)
	{}

	public static function getPresenceByHandle($handle)
	{
		$stmt = self::$db->prepare("SELECT * FROM `presences` WHERE `handle` = :handle");
		$stmt->execute(array(':handle' => $handle));
		$internals = $stmt->fetch(PDO::FETCH_ASSOC);
		$type = new Model_PresenceType($internals['type']);
		$provider = $type->getProvider(self::$db);
		return new Model_Presence($internals, $provider);
	}

	public static function getPresencesByType(Model_PresenceType $type)
	{}

	public static function getPresencesByCampaign(Model_Campaign $campaign)
	{}

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