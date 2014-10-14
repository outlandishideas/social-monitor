<?php

abstract class Badge_Factory
{
	protected static $badges = array();

	protected static function getClassName($name)
	{
		$classNames = array(
			Badge_Reach::getName() => 'Badge_Reach',
			Badge_Quality::getName() => 'Badge_Quality',
			Badge_Total::getName() => 'Badge_Total',
			Badge_Engagement::getName() => 'Badge_Engagement'
		);
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