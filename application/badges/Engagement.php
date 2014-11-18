<?php

class Badge_Engagement extends Badge_Abstract
{
	protected static $name = 'engagement';
	protected static $title = 'Engagement';
	protected static $description = '<p>The Engagement KPI provides an overall score for how well the presence, country or SBU engages with its audience. This score combines the following metrics:</p>
						<ul>
							<li>The ratio of replies by the presence owner to the number of posts / tweets by others.</li>
							<li>The average time it takes to reply to a post / tweet.</li>
							<li>The Klout Score for this presence (Twitter Only).</li>
							<li>The Facebook Engagement score for this presence (Facebook Only).</li>
						</ul>';

    public function __construct(PDO $db = null)
    {
        parent::__construct($db);
        $this->metrics = array(
            Metric_Klout::getInstance(),
            Metric_FBEngagement::getInstance(),
            Metric_ResponseTime::getInstance(),
            Metric_ResponseRatio::getInstance()
        );
    }
}