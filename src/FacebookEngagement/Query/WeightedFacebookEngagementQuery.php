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

class WeightedFacebookEngagementQuery implements Query
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
					(((f.comments*4 + f.likes + f.share_count*7) / 12) / ph.popularity) * 1000 AS `total`
                FROM
				    (
						SELECT
							presence_id,
							SUM(comments) AS comments,
							SUM(likes) AS likes,
							SUM(share_count) AS share_count
						FROM
							$facebookStream
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
                            MAX(`value`) AS popularity
                        FROM
                            $presenceHistory
                        WHERE
                            DATE(datetime) = :now
                        AND
                            `type` = '$popularity'
						GROUP BY presence_id
                    ) AS ph ON f.presence_id = ph.presence_id";

        $statement = $this->db->prepare($sql);
        $statement->execute([
            ':now' => $now->format('Y-m-d'),
            ':then' => $then->format('Y-m-d')
        ]);
        return $statement->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}