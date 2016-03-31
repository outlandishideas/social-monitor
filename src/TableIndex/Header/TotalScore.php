<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class TotalScore extends BadgeScore {

	const NAME = "total-score";

	public function __construct($translator)
	{
		parent::__construct($translator, self::NAME, \Badge_Total::NAME);
	}

}