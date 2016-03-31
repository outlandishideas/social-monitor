<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class QualityRank extends BadgeRank {

	const NAME = "quality-rank";

	public function __construct($translator)
	{
		parent::__construct($translator, self::NAME, \Badge_Quality::NAME . "_rank");
	}

}