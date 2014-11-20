<?php

abstract class Header_Factory
{
	protected static $headers = array();

	public static function getHeaderNames()
	{
		return array_keys(self::getHeaders());
	}

	public static function getHeader($name)
	{
		if (!array_key_exists($name, self::getHeaders())) {
            throw new Exception('Invalid header name: ' . $name);
		}
		return self::$headers[$name];
	}

	public static function getHeaders()
	{
        if (empty(self::$headers)) {
            /** @var Header_Abstract[] $headers */
            $headers = array(
                new Header_Branding(),
                new Header_SignOff(),
                new Header_Compare(),
                new Header_DigitalPopulation(),
                new Header_DigitalPopulationHealth(),
                new Header_ReachRank(),
                new Header_ReachScore(),
                new Header_QualityRank(),
                new Header_QualityScore(),
                new Header_EngagementRank(),
                new Header_EngagementScore(),
                new Header_TotalRank(),
                new Header_TotalScore(),
                new Header_PresenceCount(),
                new Header_Presences(),
                new Header_Options(),
                new Header_Handle(),
                new Header_TargetAudience(),
                new Header_CurrentAudience(),
                new Header_Name(),
                new Header_Country(),
                new Header_CountryCount(),
                new Header_Countries(),
                new Header_PercentTargetAudience(),
                new Header_ActionsPerDay(),
                new Header_ResponseTime()
            );
            self::$headers = array();
            foreach ($headers as $h) {
                self::$headers[$h->getName()] = $h;
            }
        }
		return self::$headers;
	}

}