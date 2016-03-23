<?php

namespace Outlandish\SocialMonitor\PresenceType;

class InstagramType extends PresenceType
{
	const NAME = 'instagram';
	
	/**
	 * @param \Metric_Abstract $engagementMetric
	 * @param \Metric_Abstract[] $metrics
	 */
	public function __construct($engagementMetric, $metrics)
	{
		parent::__construct(self::NAME, "fa fa-instagram", "Instagram", "instagram_relevance_percentage", $engagementMetric, $metrics);
	}


}