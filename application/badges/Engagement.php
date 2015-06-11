<?php

class Badge_Engagement extends Badge_Abstract
{
	protected static $name = 'engagement';
	protected static $title = 'Engagement';
	protected static $description = '<p>The Engagement KPI provides an overall score for how well the presence, country or SBU engages with its audience. This score combines the following metrics:</p>
						<ul>
							<li>The Klout Score for this presence (Twitter Only).</li>
							<li>The Facebook Engagement score for this presence (Facebook Only).</li>
						</ul>';

    public function __construct(PDO $db = null)
    {
        parent::__construct($db);
        $this->metrics = array(
            Metric_Klout::getInstance(),
            Metric_FBEngagementLeveled::getInstance(),
//            Metric_ResponseTimeNew::getInstance(),
//            Metric_ResponseRatio::getInstance()
        );
    }
}