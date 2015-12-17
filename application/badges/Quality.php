<?php

class Badge_Quality extends Badge_Abstract
{
	protected static $name = 'quality';
	protected static $title = 'Quality';
	protected static $description = '<p>The Quality KPI provides an overall score for the quality of the posts produced by the presence or presences in a Country or SBU. This score combines the following metrics:</p>
						<ul>
							<li>The average number of posts / tweets per day.</li>
							<li>The average number of links per day.</li>
							<li>The average number of likes / retweets per post / tweet.</li>
							<li>The Sign Off status of the presence.</li>
							<li>The Branding status of the presence.</li>
							<li>The number of relevant posts made each day.</li>
							<li>The Average Response Time to comments for this presence.</li>
						</ul>';

    public function __construct(PDO $db = null)
    {
        parent::__construct($db);
        $this->metrics = array(
            Metric_SignOff::getInstance(),
            Metric_Relevance::getInstance(),
            Metric_Branding::getInstance(),
            Metric_ActionsPerDay::getInstance(),
            Metric_ResponseTimeNew::getInstance(),
            Metric_LikesPerPost::getInstance()
        );
    }
}