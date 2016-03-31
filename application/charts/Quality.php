<?php

class Chart_Quality extends Chart_Badge {

    const NAME = "quality";

    public function __construct(PDO $db, $translator)
    {
        parent::__construct($db, $translator, self::NAME);
        $this->dataColumns = array(
            Badge_Quality::NAME
        );
    }

}