<?php

class Badge_Reach extends Badge_Abstract
{
	const NAME = 'reach';

	public function __construct(PDO $db, $metrics)
	{
		parent::__construct(self::NAME, $db, $metrics);
	}

}