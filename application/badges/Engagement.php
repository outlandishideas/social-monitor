<?php

class Badge_Engagement extends Badge_Abstract
{
	const NAME = 'engagement';

	public function __construct(PDO $db, $metrics)
	{
		parent::__construct(self::NAME, $db, $metrics);
	}

}