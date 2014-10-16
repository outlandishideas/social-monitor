<?php

abstract class Header_Factory
{
	protected static $headers = array();

	protected static function getClassName($name)
	{
		$classNames = self::getClassNames();
		return $classNames[$name];
	}

	public static function getClassNames()
	{
		return array(
			Header_Branding::getName() => 'Header_Branding',
			Header_SignOff::getName() => 'Header_SignOff',
			Header_Compare::getName() => 'Header_Compare',
			Header_DigitalPopulation::getName() => 'Header_DigitalPopulation',
			Header_DigitalPopulationHealth::getName() => 'Header_DigitalPopulationHealth',
			Header_ReachRank::getName() => 'Header_ReachRank',
			Header_ReachScore::getName() => 'Header_ReachScore',
			Header_QualityRank::getName() => 'Header_QualityRank',
			Header_QualityScore::getName() => 'Header_QualityScore',
			Header_EngagementRank::getName() => 'Header_EngagementRank',
			Header_EngagementScore::getName() => 'Header_EngagementScore',
			Header_TotalRank::getName() => 'Header_TotalRank',
			Header_TotalScore::getName() => 'Header_TotalScore',
			Header_Presences::getName() => 'Header_Presences',
			Header_Options::getName() => 'Header_Options',
			Header_Handle::getName() => 'Header_Handle',
			Header_TargetAudience::getName() => 'Header_TargetAudience',
			Header_CurrentAudience::getName() => 'Header_CurrentAudience',
			Header_Name::getName() => 'Header_Name',
			Header_Country::getName() => 'Header_Country'
		);
	}

	public static function getHeaderNames()
	{
		$classNames = self::getClassNames();
		return array_keys($classNames);
	}

	public static function getHeader($name)
	{
		if (!array_key_exists($name, self::$headers)) {
			$className = static::getClassName($name);
			self::$headers[$name] = new $className();
		}
		return self::$headers[$name];
	}

	public static function getHeaders()
	{
		$badges = array();
		foreach(self::getBadgeNames() as $name){
			$badges[$name] = self::getBadge($name);
		}
		return $badges;
	}
}