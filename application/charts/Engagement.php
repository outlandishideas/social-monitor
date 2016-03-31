<?php

class Chart_Engagement extends Chart_Badge {

    const NAME = "engagement";

    public function __construct(PDO $db, $translator)
    {
        parent::__construct($db, $translator, self::NAME);
        $this->dataColumns = array(
            Badge_Engagement::NAME
        );
    }

}