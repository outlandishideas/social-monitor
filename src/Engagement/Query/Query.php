<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 30/04/2015
 * Time: 13:57
 */

namespace Outlandish\SocialMonitor\Engagement\Query;

use DateTime;

interface Query {

    public function fetch(DateTime $now, DateTime $then);

}