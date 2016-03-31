<?php

use Outlandish\SocialMonitor\Engagement\Query\Query;

class Metric_FBEngagement extends Metric_AbstractEngagement {

	const NAME = "facebook_engagement";
	
	public function __construct($translator, Query $query)
	{
		parent::__construct($translator, self::NAME, "fa fa-facebook-square", $query, 0.15);
	}


}