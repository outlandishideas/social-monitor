<?php

class Badge_Engagement extends Badge_Abstract
{
	protected static $name = 'engagement';
	protected static $title = 'Engagement';
	protected static $description = '<p>The overall score KPI provides an overall score for how well a social media presence, country or SBU is doing in the other three KPIs. The score combines the total score of the following three KPIs:</p>
                <ul>
                    <li>Reach KPI</li>
                    <li>Engagement KPI</li>
                    <li>Quality KPI</li>
                </ul>';

	protected $metrics = array(
		"Metric_Klout",
		"Metric_FBEngagement",
		"Metric_ResponseTime",
		"Metric_ResponseRatio"
	);
}