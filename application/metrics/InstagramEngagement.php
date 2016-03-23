<?php

use Outlandish\SocialMonitor\Engagement\Query\Query;

class Metric_InstagramEngagement extends Metric_AbstractEngagement {

	public function __construct(Query $query)
	{
		parent::__construct("instagram_engagement", "fa fa-instagram", $query, 0.75);
	}


}