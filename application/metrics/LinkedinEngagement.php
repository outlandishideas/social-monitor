<?php

use Outlandish\SocialMonitor\Engagement\Query\Query;

class Metric_LinkedinEngagement extends Metric_AbstractEngagement {

	public function __construct(Query $query)
	{
		parent::__construct("linkedin_engagement", "fa fa_linkedin", $query, 0.25);
	}

}