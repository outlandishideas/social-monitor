<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class EngagementRank extends BadgeRank {

	const NAME = "engagement-rank";

	public function __construct($translator)
	{
		parent::__construct($translator, self::NAME, \Badge_Engagement::NAME . "_rank");
	}

}