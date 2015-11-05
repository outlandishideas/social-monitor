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

class WeightedSinaWeiboEngagementQuery implements Query
{
    const POPULARITY = 'popularity';
    const FACEBOOK_STREAM_TABLE = 'sina_weibo_posts';
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
        $stream = self::FACEBOOK_STREAM_TABLE;
        $presenceHistory = self::PRESENCE_STREAM_TABLE;

        $sql = "SELECT
                    f.presence_id,
					(((f.comments*4 + f.likes + f.share_count*7) / 12) / IFNULL(ph.popularity>0, 1)) AS `total`
                FROM
				    (
						SELECT
							presence_id,
							SUM(comment_count) AS comments,
							SUM(attitude_count) AS likes,
							SUM(repost_count) AS share_count
						FROM
							$stream
						WHERE
							DATE(created_at) >= :then
						AND
							DATE(created_at) <= :now
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
        $statement->execute([
            ':now' => $now->format('Y-m-d'),
            ':then' => $then->format('Y-m-d')
        ]);
        return $statement->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}