<?php

use Outlandish\SocialMonitor\Engagement\Query\Query;

class Metric_LinkedinEngagement extends Metric_AbstractEngagement {

	const NAME = "linkedin_engagement";

	public function __construct($translator, Query $query)
	{
		parent::__construct($translator, self::NAME, "fa fa_linkedin", $query, 0.25);
	}

}