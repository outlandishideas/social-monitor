<?php

class Chart_Reach extends Chart_Badge {

    protected static $title = "KPI: Reach";
    protected static $name = "reach";

    public function __construct(PDO $db = null)
    {
        parent::__construct($db);
        $this->yLabel = "Reach Score";
        $this->dataColumns = array(
            Badge_Reach::NAME
        );
    }
}