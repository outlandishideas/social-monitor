<?php

abstract class PresenceFactory
{

	protected $db;

	public static function getPresenceById($id)
	{}

	public static function getPresenceByHandle($handle)
	{}

	public static function getPrecensesByType(PresenceType $type)
	{}

	public static function getPrecensesByCampaign(Model_Campaign $campaign)
	{}

	public static function setDatabase(PDO $db)
	{
		self::$db = $db;
	}
}