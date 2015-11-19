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

class WeightedInstagramEngagementQuery implements Query
{
    const POPULARITY = 'popularity';
    const STREAM_TABLE = 'instagram_stream';
    const PRESENCE_STREAM_TABLE = 'presence_history';
    /**
     * @var
     */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @param DateTime $now
     * @param DateTime $then
     *
     * @return array|null
     */
    public function fetch(DateTime $now, DateTime $then)
    {
        //take one day off as otherwise this calculates without a full day of data
        //if $now = today
        $now->modify('-1 day');
        $then->modify('-1 day');
        $popularity = self::POPULARITY;
        $stream = self::STREAM_TABLE;
        $presenceHistory = self::PRESENCE_STREAM_TABLE;

        $sql = "SELECT
                    f.presence_id,
					(((f.comment_count*4 + f.like_count) / 5) / IFNULL(ph.popularity>0, 1)) AS `total`
                FROM
				    (
						SELECT
							presence_id,
							SUM(comments) AS comment_count,
							SUM(likes) AS like_count
						FROM
							$stream
						WHERE
							DATE(created_time) >= :then
						AND
							DATE(created_time) <= :now
						GROUP BY
							presence_id
					) AS f
                LEFT JOIN
                    (
                        SELECT
                            presence_id,
                            IF(MAX(`value`)>0,MAX(`value`),1) AS popularity
                        FROM
                            $presenceHistory
                        WHERE
                            DATE(datetime) = :now
                        AND
                            `type` = '$popularity'
						GROUP BY presence_id
                    ) AS ph ON f.presence_id = ph.presence_id";

        $statement = $this->db->prepare($sql);
        $success = $statement->execute([
            ':now' => $now->format('Y-m-d'),
            ':then' => $then->format('Y-m-d')
        ]);
        $value = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
        return $value;
    }
}