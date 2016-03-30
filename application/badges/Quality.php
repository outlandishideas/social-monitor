<?php

class Badge_Quality extends Badge_Abstract
{
	const NAME = 'quality';
	
	public function __construct(PDO $db, $translator, $metrics)
	{
		parent::__construct($translator, self::NAME, $db, $metrics);
	}

}