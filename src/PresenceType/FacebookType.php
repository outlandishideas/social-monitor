<?php

namespace Outlandish\SocialMonitor\PresenceType;

class FacebookType extends PresenceType
{
	const NAME = 'facebook';

	/**
	 * @param \Metric_Abstract $engagementMetric
	 * @param \Metric_Abstract[] $metrics
	 */
	public function __construct($engagementMetric, $metrics)
	{
		parent::__construct(self::NAME, "fa fa-facebook", "Facebook", "facebook_relevance_percentage", $engagementMetric, $metrics);
	}


}