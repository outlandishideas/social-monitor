<?php

namespace Outlandish\SocialMonitor\Query;

use Outlandish\SocialMonitor\Database\Database;

abstract class HistoryDataQuery
{
    /**
     * @var Database
     */
    protected $db;

    /**
     * @param Database $db
     */
    public function __construct(Database $db) {
        $this->db = $db;
    }

    abstract function get(\DateTime $startDate, \DateTime $endDate);
}