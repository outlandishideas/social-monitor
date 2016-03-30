<?php

class Badge_Engagement extends Badge_Abstract
{
	const NAME = 'engagement';

	public function __construct(PDO $db, $translator, $metrics)
	{
		parent::__construct($translator, self::NAME, $db, $metrics);
	}

}