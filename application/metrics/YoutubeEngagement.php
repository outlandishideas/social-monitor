<?php

use Outlandish\SocialMonitor\Engagement\Query\Query;

class Metric_YoutubeEngagement extends Metric_AbstractEngagement {

	const NAME = "youtube_engagement";
	
	public function __construct($translator, Query $query)
	{
		parent::__construct($translator, self::NAME, "fa fa-youtube", $query, 1.5);
	}

}