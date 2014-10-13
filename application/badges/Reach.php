<?php

class Badge_Reach extends Badge_Abstract
{
	protected static $name = 'reach';
	protected static $title = "Reach";

	public $metrics = array(
		"Metric_Popularity",
		"Metric_PopularityTime"
	);

}