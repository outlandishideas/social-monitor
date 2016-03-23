<?php

use Outlandish\SocialMonitor\Engagement\Query\Query;

class Metric_FBEngagement extends Metric_AbstractEngagement {

	public function __construct(Query $query)
	{
		parent::__construct("facebook_engagement", "fa fa-facebook-square", $query, 0.15);
	}


}