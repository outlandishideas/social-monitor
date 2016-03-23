<?php

use Outlandish\SocialMonitor\Engagement\Query\Query;

class Metric_SinaWeiboEngagement extends Metric_AbstractEngagement {

	public function __construct(Query $query)
	{
		parent::__construct("sina_weibo_engagement", "fa fa-weibo", $query, 0.5);
	}

}