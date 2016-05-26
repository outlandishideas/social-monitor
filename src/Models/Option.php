<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 24/05/2016
 * Time: 12:51
 */

namespace Outlandish\SocialMonitor\Models;


use Outlandish\SocialMonitor\Database\Database;

class Option
{
	/** @var Database */
	private static $db;
	/** @var array */
	private static $optionCache = [];

	/**
	 * Store some arbitrary value in the options table
	 * @static
	 * @param $name string Option name
	 * @param $value string Option value
	 * @return void
	 */
	public static function setOption($name, $value)
	{
		self::setOptions(array($name=>$value));
	}

	/**
	 * Sets multiple options. Options passed as key value array
	 *
	 * @param array $options
	 */
	public static function setOptions($options) {
		$options = (array)$options;
		$statement = self::db()->prepare('REPLACE INTO options (name, value) VALUES (:name, :value)');
		foreach ($options as $name=>$value) {
			$statement->execute(array(':name' => $name, ':value' => $value));
			if (array_key_exists($name, self::$optionCache)) {
				unset(self::$optionCache[$name]);
			}
		}
	}

	/**
	 * Fetch back an option
	 *
	 * @param $name string Option name
	 * @return string|bool The stored value or false if no value exists
	 */
	public static function getOption($name)
	{
		if (!array_key_exists($name, self::$optionCache)) {
			$sql = 'SELECT value FROM options WHERE name = :name LIMIT 1';
			$statement = self::db()->prepare($sql);
			$statement->execute(array(':name' => $name));
			$value = $statement->fetchColumn();
			self::$optionCache[$name] = $value;
		}

		return self::$optionCache[$name];
	}

	/**
	 * @return Database
	 */
	private static function db()
	{
		return self::$db;
	}

	/**
	 * Set the database object for this class to be used statically
	 *
	 * @param Database $db
	 */
	public static function setDb(Database $db)
	{
		self::$db = $db;
	}
}