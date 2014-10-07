<?php

abstract class NewModel_PresenceFactory
{

	protected static $db;

	public static function getPresences()
	{
		$stmt = self::$db->prepare("SELECT * FROM `presences`");
		$stmt->execute();
		$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if($results == false) return array();

		$presences = array_map(function($internals){
			if($internals != false) {
				$type = new NewModel_PresenceType($internals['type']);
				$provider = $type->getProvider(self::$db);
				return new NewModel_Presence(self::$db, $internals, $provider);
			} else {
				return null;
			}
		}, $results);

		return array_filter($presences);
	}

	public static function getPresenceById($id)
	{
		$stmt = self::$db->prepare("SELECT * FROM `presences` WHERE `id` = :id");
		$stmt->execute(array(':id' => $id));
		$internals = $stmt->fetch(PDO::FETCH_ASSOC);

		if($internals != false) {
			$type = new NewModel_PresenceType($internals['type']);
			$provider = $type->getProvider(self::$db);
			return new NewModel_Presence(self::$db, $internals, $provider);
		} else {
			return null;
		}
	}

	public static function getPresenceByHandle($handle, NewModel_PresenceType $type)
	{
		$stmt = self::$db->prepare("SELECT * FROM `presences` WHERE `handle` = :handle AND `type` = :t");
		$stmt->execute(array(':handle' => $handle, ':t' => $type));
		$internals = $stmt->fetch(PDO::FETCH_ASSOC);

		if($internals != false) {
			$provider = $type->getProvider(self::$db);
			return new NewModel_Presence(self::$db, $internals, $provider);
		} else {
			return null;
		}
	}

	public static function getPresencesByType(NewModel_PresenceType $type)
	{
		$stmt = self::$db->prepare("SELECT * FROM `presences` WHERE `type` = :type");
		$stmt->execute(array(':type' => $type));
		$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if($results == false) return array();

		$presences = array_map(function($internals){
			if($internals != false) {
				$type = new NewModel_PresenceType($internals['type']);
				$provider = $type->getProvider(self::$db);
				return new NewModel_Presence(self::$db, $internals, $provider);
			} else {
				return null;
			}
		}, $results);

		return $presences;
	}

	public static function getPresencesById(array $ids)
	{
		$inQuery = implode(',', array_fill(0, count($ids), '?'));
		$stmt = self::$db->prepare("SELECT * FROM `presences` WHERE `id` IN ({$inQuery})");
		$stmt->execute($ids);
		$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if($results == false) return array();

		$presences = array_map(function($internals){
			if($internals != false) {
				$type = new NewModel_PresenceType($internals['type']);
				$provider = $type->getProvider(self::$db);
				return new NewModel_Presence(self::$db, $internals, $provider);
			} else {
				return null;
			}
		}, $results);

		return array_filter($presences);
	}

	public static function getPresencesByCampaign($campaign)
	{
		$stmt = self::$db->prepare("SELECT presence_id FROM `campaign_presences` WHERE `campaign_id` = :cid");
		$stmt->execute(array(":cid" => $campaign));
		$presence_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

		if($presence_ids == false) return array();

		return self::getPresencesById($presence_ids);
	}

	public static function createNewPresence(NewModel_PresenceType $type, $handle, $signed_off, $branding)
	{
		$signed_off = !!$signed_off;
		$branding = !!$branding;

		$provider = $type->getProvider(self::$db);

		$args = $provider->testHandle($handle);

		if (false === $args) {
			return false;
		} else {
			//insert presence
			$stmt = self::$db->prepare("
				INSERT INTO `presences`
				(`type`, `handle`, `uid`, `image_url`, `name`, `page_url`, `popularity`, `last_updated`, `sign_off`, `branding`)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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