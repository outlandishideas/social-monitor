<?php

namespace Outlandish\SocialMonitor\Engagement\Query;


use BaseController;
use PDO;

class WeightedSinaWeiboEngagementQuery extends Query
{
    const STATUS_TABLE = 'sina_weibo_posts';
    const CREATED_COLUMN = 'created_at';

    protected $engagementWeighting = ['attitude_count'=>1,'comment_count'=>4,'repost_count'=>7];

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->activeUserProportion = array();
        $this->activeUserProportion[0] = BaseController::getOption('sw_active_user_percentage_small') / 100;
        $this->activeUserProportion[1] = BaseController::getOption('sw_active_user_percentage_medium') / 100;
        $this->activeUserProportion[2] = BaseController::getOption('sw_active_user_percentage_large') / 100;
        $this->activeUserProportion[3] = BaseController::getOption('sw_active_user_percentage_large') / 100;
    }

}