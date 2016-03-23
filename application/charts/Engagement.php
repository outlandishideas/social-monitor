<?php

class Chart_Engagement extends Chart_Badge {

    protected static $name = "engagement";

    public function __construct(PDO $db = null)
    {
        parent::__construct($db);
		$this->yLabel = $this->translate->_(get_class($this).".y-axis-label");
        $this->dataColumns = array(
            Badge_Engagement::NAME
        );
    }

}