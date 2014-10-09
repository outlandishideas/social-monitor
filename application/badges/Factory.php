<?php

abstract class Badge_Factory
{
	protected static $badges = array();

	protected static function getClassName($name)
	{
		$classNames = array();
		return $classNames[$name];
	}

	public static function getBadge($name)
	{
		if (!array_key_exists($name, self::$badges)) {
			$className = static::getClassName($name);
			self::$badges[$name] = new $className;
		}
		return self::$badges[$name];
	}
}