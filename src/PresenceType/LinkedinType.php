<?php

namespace Outlandish\SocialMonitor\PresenceType;

class LinkedinType extends PresenceType
{
	const NAME = 'linkedin';
	
	/**
	 * @param \Metric_Abstract $engagementMetric
	 * @param \Metric_Abstract[] $metrics
	 */
	public function __construct($engagementMetric, $metrics)
	{
		parent::__construct(self::NAME, "fa fa-linkedin", "LinkedIn", 10, $engagementMetric, $metrics, true);
	}


}