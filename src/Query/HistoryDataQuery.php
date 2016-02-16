<?php

namespace Outlandish\SocialMonitor\Query;

use PDO;

abstract class HistoryDataQuery
{
    /**
     * @var PDO
     */
    protected $db;

    /**
     * @param \PDO $db
     */
    public function __construct(\PDO $db) {
        $this->db = $db;
    }

    abstract function get(\Datetime $startDate, \DateTime $endDate);
}