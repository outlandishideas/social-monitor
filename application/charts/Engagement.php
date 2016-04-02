<?php

use Outlandish\SocialMonitor\Database\Database;

class Chart_Engagement extends Chart_Badge {

    const NAME = "engagement";

    public function __construct(Database $db, $translator)
    {
        parent::__construct($db, $translator, self::NAME);
        $this->dataColumns = array(
            Badge_Engagement::NAME
        );
    }

}