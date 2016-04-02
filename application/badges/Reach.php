<?php

class Badge_Reach extends Badge_Abstract
{
	const NAME = 'reach';

	public function __construct($db, $translator, $metrics)
	{
		parent::__construct($db, $translator, self::NAME, $metrics);
	}

}