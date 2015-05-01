<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 30/04/2015
 * Time: 13:57
 */

namespace Outlandish\SocialMonitor\FacebookEngagement\Query;


use DateTime;
use PDO;

class StandardFacebookEngagementQuery implements Query
{
    const POPULARITY = 'popularity';
    const FACEBOOK_STREAM_TABLE = 'facebook_stream';
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
        $popularity = self::POPULARITY;
        $facebookStream = self::FACEBOOK_STREAM_TABLE;
        $presenceHistory = self::PRESENCE_STREAM_TABLE;

        $sql = "SELECT
                    f.presence_id,
                    (((SUM(f.comments) + SUM(f.likes) + SUM(f.share_count)) / MAX(ph.popularity))*100) AS `total`
                FROM
                    $facebookStream AS f
                LEFT JOIN
                    (
                        SELECT
                            presence_id,
                            `value` AS popularity
                        FROM
                            $presenceHistory
                        WHERE
                            DATE(datetime) = :now
                        AND
                            `type` = :popularity
                    ) AS ph ON f.presence_id = ph.presence_id
                WHERE
                    DATE(f.created_time) >= :then
                GROUP BY
                    presence_id";

        $statement = $this->db->prepare($sql);
        $statement->execute([
            ':now' => $now->format('Y-m-d'),
            ':then' => $then->format('Y-m-d'),
            ':popularity' => $popularity
        ]);
        return $statement->fetchAll(PDO::FETCH_KEY_PAIR);
    }

}