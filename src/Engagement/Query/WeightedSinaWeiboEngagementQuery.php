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
    const STREAM_TABLE = 'sina_weibo_posts';
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
     * @return array|null
     */
    public function getData(DateTime $now, DateTime $then)
    {
        //take one day off as otherwise this calculates without a full day of data
        //if $now = today
        $now->modify('-1 day');
        $then->modify('-1 day');
        $popularity = self::POPULARITY;
        $stream = self::STREAM_TABLE;
        $presenceHistory = self::PRESENCE_STREAM_TABLE;
        $presencesTable = self::PRESENCES_TABLE;

        // extract the size so we can then scale according to the active user proportions for small/medium/large presences
        $sql = "SELECT
                    ph.presence_id AS `presence_id`,
                    ph.size,
      				f.likes,
					f.comments,
					f.share_count,
					f.likes AS `weighted_likes`,
                    (f.comments*4) AS `weighted_comments`,
                    (f.share_count*7) AS `weighted_shares`,
					ph.popularity
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
                RIGHT JOIN
                    (
                        SELECT
                            p.id as presence_id,
                            p.size as size,
                            h.popularity as popularity
                        FROM (SELECT
						        id,
						        size
						    FROM $presencesTable WHERE type='sina_weibo') as p
						INNER JOIN
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
						ON p.id = h.presence_id
                    ) AS ph ON f.presence_id = ph.presence_id";

        $statement = $this->db->prepare($sql);
        $statement->execute([
            ':now' => $now->format('Y-m-d'),
            ':then' => $then->format('Y-m-d')
        ]);
        $data = $statement->fetchAll(PDO::FETCH_ASSOC);
        // create key => value array, scaling by the active user proportion
        foreach($data as &$d) {
            $scale = $this->activeUserProportion[$d['size']] ? $this->activeUserProportion[$d['size']] : 1;
            $d['active_users'] = $scale * $d['popularity'];
            $d['weighted_engagement'] = ($d['weighted_likes'] + $d['weighted_comments'] + $d['weighted_shares']) / 12;
            $d['scaled_engagement'] = $d['active_users'] ? ($d['weighted_engagement'] / $d['active_users']) * 1000 : null;
        }
        return $data;
    }
}