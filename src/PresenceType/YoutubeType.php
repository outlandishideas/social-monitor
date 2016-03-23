<?php

namespace Outlandish\SocialMonitor\PresenceType;

class YoutubeType extends PresenceType
{
	const NAME = 'youtube';
	
	/**
	 * @param \Metric_Abstract $engagementMetric
	 * @param \Metric_Abstract[] $metrics
	 */
	public function __construct($engagementMetric, $metrics)
	{
		parent::__construct(self::NAME, "fa fa-youtube", "Youtube", 10, $engagementMetric, $metrics);
	}


}