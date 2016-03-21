<?php

class Badge_Engagement extends Badge_Abstract
{
    protected static $instance;

    protected function __construct(PDO $db = null)
    {
        parent::__construct($db);
        $this->metrics = array(
            Metric_Klout::getInstance(),
            Metric_FBEngagement::getInstance(),
            Metric_SinaWeiboEngagement::getInstance(),
            Metric_InstagramEngagement::getInstance(),
            Metric_YoutubeEngagement::getInstance(),
            Metric_LinkedinEngagement::getInstance()
        );
    }
}