<?php

class Badge_Quality extends Badge_Abstract
{
	const NAME = 'quality';
	
	public function __construct($db, $translator, $metrics)
	{
		parent::__construct($db, $translator, self::NAME, $metrics);
	}

}