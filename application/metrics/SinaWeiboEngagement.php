<?php

use Outlandish\SocialMonitor\Engagement\Query\Query;

class Metric_SinaWeiboEngagement extends Metric_AbstractEngagement {

	const NAME = "sina_weibo_engagement";

	public function __construct($translator, Query $query)
	{
		parent::__construct($translator, self::NAME, "fa fa-weibo", $query, 0.5);
	}

}