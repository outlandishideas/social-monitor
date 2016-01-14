<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 30/04/2015
 * Time: 13:57
 */

namespace Outlandish\SocialMonitor\Engagement\Query;


use BaseController;
use DateTime;
use PDO;

class WeightedSinaWeiboEngagementQuery implements Query
{
    const POPULARITY = 'popularity';
    const FACEBOOK_STREAM_TABLE = 'sina_weibo_posts';
    const PRESENCE_STREAM_TABLE = 'presence_history';
    const PRESENCES_TABLE = 'presences';
    /**
     * @var
     */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->activeUserProportion = array();
        $this->activeUserProportion[0] = BaseController::getOption('sw_active_user_percentage_small') / 100;
        $this->activeUserProportion[1] = BaseController::getOption('sw_active_user_percentage_medium') / 100;
        $this->activeUserProportion[2] = BaseController::getOption('sw_active_user_percentage_large') / 100;
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
        $presencesTable = self::PRESENCES_TABLE;

        // extract the size so we can then scale according to the active user proportions for small/medium/large presences
        $sql = "SELECT
                    f.presence_id,
                    ph.size,
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
                            p.id as presence_id,
                            p.size as size,
                            h.popularity as popularity
                        FROM
                            (SELECT
						        id,
						        size
						    FROM $presencesTable) as p
						LEFT JOIN
						    (SELECT
                                presence_id,
                                IF(MAX(`value`)>0,MAX(`value`),1) AS popularity
                            FROM
                                $presenceHistory
                            WHERE
                                DATE(datetime) = :now
                            AND
                                `type` = '$popularity'
						    GROUP BY presence_id) as h
						ON h.presence_id = p.id
                    ) AS ph ON f.presence_id = ph.presence_id";

        $statement = $this->db->prepare($sql);
        $statement->execute([
            ':now' => $now->format('Y-m-d'),
            ':then' => $then->format('Y-m-d')
        ]);
        $data = $statement->fetchAll(PDO::FETCH_ASSOC);
        $scores = array();
        // create key => value array, scaling by the active user proportion
        foreach($data as $d) {
            $scale = $this->activeUserProportion[$d['size']] ? $this->activeUserProportion[$d['size']] : 1;
            $scores[$d['presence_id']] = $d['total'] / $scale;
        }
        return $scores;
    }
}