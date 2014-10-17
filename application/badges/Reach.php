<?php

class Badge_Reach extends Badge_Abstract
{
	protected static $name = 'reach';
	protected static $title = "Reach";
	protected static $description = '<p>The overall score KPI provides an overall score for how well a social media presence, country or SBU is doing in the other three KPIs. The score combines the total score of the following three KPIs:</p>
                <ul>
                    <li>Reach KPI</li>
                    <li>Engagement KPI</li>
                    <li>Quality KPI</li>
                </ul>';

	public $metrics = array(
		"Metric_Popularity",
		"Metric_PopularityTime"
	);

}