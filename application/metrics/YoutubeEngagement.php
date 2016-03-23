<?php

use Outlandish\SocialMonitor\Engagement\Query\Query;

class Metric_YoutubeEngagement extends Metric_AbstractEngagement {

	public function __construct(Query $query)
	{
		parent::__construct("youtube_engagement", "fa fa-youtube", $query, 1.5);
	}

}