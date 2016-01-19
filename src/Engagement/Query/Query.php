<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 30/04/2015
 * Time: 13:57
 */

namespace Outlandish\SocialMonitor\Engagement\Query;

use DateTime;
use PDO;

abstract class Query {

    /** @var PDO */
    protected $db;
    protected $engagementWeighting = array();
    protected $activeUserProportion = array();
    const PRESENCES_TABLE = 'presences';
    const POPULARITY = 'popularity';
    const CREATED_COLUMN = 'created_time';
    const PRESENCE_STREAM_TABLE = 'presence_history';
    const STATUS_TABLE = '';

    /**
     * @param DateTime $now
     * @param DateTime $then
     *
     * @return array|null
     */
    public function fetch(DateTime $now, DateTime $then)
    {
        $rows = $this->getData($now, $then);

        $scores = [];

        foreach ($rows as $row) {
            $scores[$row['presence_id']] = $row['scaled_engagement'];
        }

        return $scores;
    }

    /**
     * @param DateTime $now
     * @param DateTime $then
     *
     * @return array [presence_id, size, likes, comments, share_count, weighted_likes, weighted_comments,
     * weighted_shares, popularity, active_users, weighted_engagement, scaled_engagement]
     */
    public function getData(DateTime $now, DateTime $then)
    {
        //take one day off as otherwise this calculates without a full day of data
        //if $now = today
        $now->modify('-1 day');
        $then->modify('-1 day');

        $sql = "SELECT
                    {$this->selectStatement()}
                FROM
				    (
                        {$this->presenceTableQuery()}
                    ) AS ph
                LEFT JOIN
                    (
						{$this->statusTableQuery()}
					) AS f
                    ON f.presence_id = ph.presence_id";

        $statement = $this->db->prepare($sql);
        $statement->execute([
            ':now' => $now->format('Y-m-d'),
            ':then' => $then->format('Y-m-d')
        ]);

        $data = $statement->fetchAll(PDO::FETCH_ASSOC);
        // create key => value array, scaling by the active user proportion
        foreach ($data as &$d) {
            $scale = $this->activeUserProportion[$d['size']] ? $this->activeUserProportion[$d['size']] : 1;
            $d['active_users'] = $scale * $d['popularity'];
            $d['scaled_engagement'] = $d['active_users'] ? ($d['weighted_engagement'] / $d['active_users']) * 1000 : 0;
        }
        return $data;
    }

    protected function presenceTableQuery()
    {
        $presenceHistory = static::PRESENCE_STREAM_TABLE;
        $presencesTable = static::PRESENCES_TABLE;
        $popularity = static::POPULARITY;

        return "SELECT
                    p.id as presence_id,
                    p.size as size,
                    IFNULL(h.popularity,0) as popularity
                FROM (SELECT
                        id,
                        size
                    FROM $presencesTable) as p
                LEFT JOIN
                    (SELECT
                        presence_id,
                        MAX(`value`) AS popularity
                    FROM
                        $presenceHistory
                    WHERE
                        DATE(datetime) = :now
                    AND
                        `type` = '$popularity'
                GROUP BY presence_id) AS h
                ON p.id = h.presence_id";
    }

    protected function statusTableQuery()
    {
        $statusTable = static::STATUS_TABLE;

        $selectClauses = ['presence_id'];
        foreach ($this->engagementWeighting as $key => $weight) {
            $selectClauses[] = "SUM($key) AS $key";
        }
        $selectClausesStr = implode(',', $selectClauses);

        $whereClauses = $this->statusTableWhereClauses();
        $whereClausesStr = implode(' AND ', $whereClauses);

        return "SELECT
                      {$selectClausesStr}
						FROM
							$statusTable
						WHERE
							$whereClausesStr
						GROUP BY
							presence_id";
    }

    protected function statusTableWhereClauses()
    {
        $createdColumn = static::CREATED_COLUMN;
        $clauses = array();
        $clauses[] = "DATE($createdColumn) >= :then";
        $clauses[] = "DATE($createdColumn) <= :now";
        return $clauses;
    }

    protected function selectStatement()
    {
        $weightedEngagement = array();
        $weightSum = 0;
        $clauses = ['ph.presence_id AS `presence_id`', 'ph.size', 'ph.popularity'];
        foreach ($this->engagementWeighting as $key => $weight) {
            $clauses[] = "IFNULL(f.$key,0) AS $key";
            $clauses[] = "IFNULL(f.$key,0)*$weight AS weighted_$key";
            $weightedEngagement[] = "f.$key*$weight";
            $weightSum += $weight;
        }
        $weightedEngagementStr = "(" . implode('+', $weightedEngagement) . ") / $weightSum AS `weighted_engagement`";

        $clauses[] = $weightedEngagementStr;
        return implode(',', $clauses);
    }

}