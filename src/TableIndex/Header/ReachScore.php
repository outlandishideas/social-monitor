<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class ReachScore extends BadgeScore {

	const NAME = "reach-score";

	public function __construct($translator)
	{
		parent::__construct($translator, self::NAME, \Badge_Reach::NAME);
	}

}