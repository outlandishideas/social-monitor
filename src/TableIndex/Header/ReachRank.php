<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class ReachRank extends BadgeRank {

	const NAME = "reach-rank";

	public function __construct($translator)
	{
		parent::__construct($translator, self::NAME, \Badge_Reach::NAME . "_rank");
	}

}