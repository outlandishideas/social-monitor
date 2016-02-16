<?php

namespace Outlandish\SocialMonitor\Query;

/**
 * @unused
 */
class PresencePopularityHistoryDataQuery extends HistoryDataQuery
{

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