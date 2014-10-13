<?php

class Badge_Engagement extends Badge_Abstract
{
	protected static $name = 'engagement';
	protected static $title = 'Engagement';

	protected $metrics = array(
		"Metric_Klout",
		"Metric_FBEngagement",
		"Metric_ResponseTime",
		"Metric_ResponseRatio"
	);
}