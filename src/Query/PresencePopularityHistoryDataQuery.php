<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 29/04/2015
 * Time: 16:58
 */

namespace Outlandish\SocialMonitor\Query;

class PresencePopularityHistoryDataQuery {

    /**
     * @var PDO
     */
    private $db;

    /**
     * @param \PDO $db
     */
    public function __construct(\PDO $db) {
        $this->db = $db;
    }

    public function get(\Datetime $startDate, \DateTime $endDate)
    {
        $sql = "SELECT
                  presence_id,
                  DATE(datetime) AS `date`,
                  MAX(value) AS `score`
                FROM
                  `presence_history`
                WHERE
                  `type` = 'popularity'
                AND
                  DATE(datetime) >= :startDate
                AND
                  DATE(datetime) <= :endDate
                GROUP BY
                  presence_id,
                  DATE(datetime);";

        $statement = $this->db->prepare($sql);
        $statement->execute([
            ':startDate' => $startDate->format('Y-m-d'),
            ':endDate' => $endDate->format('Y-m-d')
        ]);
        $data = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $data;
    }

}