<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 30/04/2015
 * Time: 13:57
 */

namespace Outlandish\SocialMonitor\Engagement\Query;


use BaseController;
use DateTime;
use PDO;

class WeightedInstagramEngagementQuery extends Query
{
    const STATUS_TABLE = 'instagram_stream';

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->activeUserProportion = array();
        $this->activeUserProportion[0] = BaseController::getOption('ig_active_user_percentage_small') / 100;
        $this->activeUserProportion[1] = BaseController::getOption('ig_active_user_percentage_medium') / 100;
        $this->activeUserProportion[2] = BaseController::getOption('ig_active_user_percentage_large') / 100;
    }

    protected $engagementWeighting = ['likes'=>1,'comments'=>4];

}