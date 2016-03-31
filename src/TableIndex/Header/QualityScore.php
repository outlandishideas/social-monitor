<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class QualityScore extends BadgeScore {

	const NAME = "quality-score";

	public function __construct($translator)
	{
		parent::__construct($translator, self::NAME, \Badge_Quality::NAME);
	}

}