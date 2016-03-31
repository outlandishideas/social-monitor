<?php

use Outlandish\SocialMonitor\Engagement\Query\Query;

class Metric_InstagramEngagement extends Metric_AbstractEngagement {

	const NAME = "instagram_engagement";

	public function __construct($translator, Query $query)
	{
		parent::__construct($translator, self::NAME, "fa fa-instagram", $query, 0.75);
	}


}