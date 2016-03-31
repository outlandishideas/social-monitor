<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class TotalRank extends BadgeRank {

	const NAME = "total-rank";

	public function __construct($translator)
	{
		parent::__construct($translator, self::NAME, \Badge_Total::NAME . "_rank");
	}

}