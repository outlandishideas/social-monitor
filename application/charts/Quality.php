<?php

class Chart_Quality extends Chart_Badge {

    protected static $title = "KPI: Quality";
    protected static $name = "quality";

    public function __construct(PDO $db = null)
    {
        parent::__construct($db);
        $this->yLabel = "Quality Score";
        $this->dataColumns = array(
            Badge_Quality::getInstance()->getName()
        );
    }

}