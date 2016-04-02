<?php

use Outlandish\SocialMonitor\Database\Database;

class Chart_Reach extends Chart_Badge {

    const NAME = "reach";

    public function __construct(Database $db, $translator)
    {
        parent::__construct($db, $translator, self::NAME);
        $this->dataColumns = array(
            Badge_Reach::NAME
        );
    }
}