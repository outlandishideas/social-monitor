<?php

class Badge_Engagement extends Badge_Abstract
{
	protected static $name = 'engagement';
	protected static $title = 'Engagement';
	protected static $description = '<p>The Engagement Badge provides an overall score for how well the presence, country or SBU engages with its audience. This score combines the following metrics:</p>
						<ul>
							<li>The ratio of replies by the presence owner to the number of posts / tweets by others.</li>
							<li>The average time it takes to reply to a post / tweet.</li>
							<li>The Klout Score for this presence (Twitter Only).</li>
							<li>The Facebook Engagement score for this presence (Facebook Only).</li>
						</ul>';

	protected $metrics = array(
		"Metric_Klout",
		"Metric_FBEngagement",
		"Metric_ResponseTime",
		"Metric_ResponseRatio"
	);
}