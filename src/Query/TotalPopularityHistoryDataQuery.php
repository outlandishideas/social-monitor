<?php

namespace Outlandish\SocialMonitor\Query;

class TotalPopularityHistoryDataQuery extends HistoryDataQuery {

    public function get(\DateTime $startDate, \DateTime $endDate)
    {
        // need to use MAX(value) as DB schema allows multiple entries per day
        $subTable = "SELECT
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
                  DATE(datetime)";
        $sql = "SELECT
                  t.date AS `date`,
                  SUM(t.score) AS `score`
                FROM
                  ($subTable) AS t
                GROUP BY
                  t.date;";

        $statement = $this->db->prepare($sql);
        $statement->execute([
            ':startDate' => $startDate->format('Y-m-d'),
            ':endDate' => $endDate->format('Y-m-d')
        ]);
        $data = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $data;
    }

}