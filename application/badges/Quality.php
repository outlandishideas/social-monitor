<?php

class Badge_Quality extends Badge_Abstract
{
	const NAME = 'quality';
	
	public function __construct(PDO $db, $metrics)
	{
		parent::__construct(self::NAME, $db, $metrics);
	}

}