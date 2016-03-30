<?php

class Badge_Reach extends Badge_Abstract
{
	const NAME = 'reach';

	public function __construct(PDO $db, $translator, $metrics)
	{
		parent::__construct($translator, self::NAME, $db, $metrics);
	}

}