<?php

class Chart_Engagement extends Chart_Badge {

    protected static $title = "KPI: Engagement";
    protected static $name = "engagement";

    public function __construct(PDO $db = null)
    {
        parent::__construct($db);
        $this->yLabel = "Reach Score";
        $this->dataColumns = array(
            Badge_Engagement::getInstance()->getName()
        );
    }

}