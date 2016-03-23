<?php

class Chart_Quality extends Chart_Badge {

    protected static $name = "quality";

    public function __construct(PDO $db = null)
    {
        parent::__construct($db);
        $this->yLabel = $this->translate->_(get_class($this).".y-axis-label");
        $this->dataColumns = array(
            Badge_Quality::NAME
        );
    }

}