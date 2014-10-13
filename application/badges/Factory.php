<?php

abstract class Badge_Factory
{
	protected static $badges = array();

	protected static function getClassName($name)
	{
		$classNames = self::getClassNames();
		return $classNames[$name];
	}

	public static function getClassNames()
	{
		return array(
			Badge_Total::getName() => 'Badge_Total',
			Badge_Reach::getName() => 'Badge_Reach',
			Badge_Engagement::getName() => 'Badge_Engagement',
			Badge_Quality::getName() => 'Badge_Quality'
		);
	}

	public static function getBadgeNames()
	{
		$classNames = self::getClassNames();
		return array_keys($classNames);
	}

	public static function getBadge($name)
	{
		if (!array_key_exists($name, self::$badges)) {
			$className = static::getClassName($name);
			self::$badges[$name] = new $className;
		}
		return self::$badges[$name];
	}

	public static function getBadges()
	{
		$badges = array();
		foreach(self::getBadgeNames() as $name){
			$badges[$name] = self::getBadge($name);
		}
		return $badges;
	}
}