<?php

namespace Outlandish\SocialMonitor\Engagement\Query;

use BaseController;
use Outlandish\SocialMonitor\Database\Database;

class WeightedFacebookEngagementQuery extends Query
{
    const STATUS_TABLE = 'facebook_stream';

    protected $engagementWeighting = ['likes'=>1,'comments'=>4,'share_count'=>7];

    public function __construct(Database $db)
    {
		parent::__construct($db);
        $this->activeUserProportion[0] = BaseController::getOption('fb_active_user_percentage_small') / 100;
        $this->activeUserProportion[1] = BaseController::getOption('fb_active_user_percentage_medium') / 100;
        $this->activeUserProportion[2] = BaseController::getOption('fb_active_user_percentage_large') / 100;
        $this->activeUserProportion[3] = BaseController::getOption('fb_active_user_percentage_xlarge') / 100;
    }


    protected function statusTableWhereClauses()
    {
        $clauses = parent::statusTableWhereClauses();
        $clauses[] = "in_response_to IS NULL";
        return $clauses;
    }
}