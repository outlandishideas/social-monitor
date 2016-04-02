<?php

class Badge_Engagement extends Badge_Abstract
{
	const NAME = 'engagement';

	public function __construct($db, $translator, $metrics)
	{
		parent::__construct($db, $translator, self::NAME, $metrics);
	}

}