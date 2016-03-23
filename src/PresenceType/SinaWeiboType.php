<?php

namespace Outlandish\SocialMonitor\PresenceType;

class SinaWeiboType extends PresenceType
{
	const NAME = 'sina_weibo';
	
	/**
	 * @param \Metric_Abstract $engagementMetric
	 * @param \Metric_Abstract[] $metrics
	 */
	public function __construct($engagementMetric, $metrics)
	{
		parent::__construct(self::NAME, "fa fa-weibo", "Sina Weibo", "sina_weibo_relevance_percentage", $engagementMetric, $metrics);
	}


}