<?php

class Badge_Reach extends Badge_Abstract
{
	protected static $name = 'reach';
	protected static $title = "Reach";
	protected static $description = '<p>The Reach Badge provides an overall score for how well the presence, country or SBU reaches its audience. This score combines the following metrics:</p>
				<ul>
					<li>Current number of Fans / Followers</li>
					<li>Number of months to reach the Target number of Fans / Followers</li>
					<li>Average number of shares / retweets for each post / tweet</li>
				</ul>';

    public function __construct(PDO $db = null)
    {
        parent::__construct($db);
        $this->metrics = array(
            Metric_Popularity::getInstance(),
            Metric_PopularityTime::getInstance()
        );
    }

}