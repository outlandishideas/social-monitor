<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class EngagementScore extends BadgeScore {

	const NAME = "engagement-score";

	public function __construct($translator)
	{
		parent::__construct($translator, self::NAME, \Badge_Engagement::NAME);
	}

}