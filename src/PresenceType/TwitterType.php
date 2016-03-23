<?php

namespace Outlandish\SocialMonitor\PresenceType;

class TwitterType extends PresenceType
{
	const NAME = 'twitter';

	/**
	 * @param \Metric_Abstract $engagementMetric
	 * @param \Metric_Abstract[] $metrics
	 */
	public function __construct($engagementMetric, $metrics)
	{
		parent::__construct(self::NAME, "fa fa-twitter", "Twitter", "twitter_relevance_percentage", $engagementMetric, $metrics);
	}


}