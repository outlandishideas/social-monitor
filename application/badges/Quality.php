<?php

class Badge_Quality extends Badge_Abstract
{
	protected static $name = 'quality';
	protected static $title = 'Quality';
	
	protected $metrics = array(
		"Metric_SignOff",
		"Metric_Relevance",
		"Metric_Branding",
		"Metric_ActionsPerDay",
		"Metric_LikesPerPost",
	);
}