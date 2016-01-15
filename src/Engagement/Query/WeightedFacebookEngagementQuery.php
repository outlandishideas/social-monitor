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

class WeightedFacebookEngagementQuery implements Query
{
    const POPULARITY = 'popularity';
    const FACEBOOK_STREAM_TABLE = 'facebook_stream';
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
        $facebookStream = self::FACEBOOK_STREAM_TABLE;
        $presenceHistory = self::PRESENCE_STREAM_TABLE;
        $presencesTable = self::PRESENCES_TABLE;

        $sql = "SELECT
                    f.presence_id,
                    ph.size,
					f.comments,
					f.likes,
					f.share_count,
					ph.popularity
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
					    AND
					        in_response_to IS NULL
						GROUP BY
							presence_id
					) AS f
                LEFT JOIN
                    (
                        SELECT
                            p.id as presence_id,
                            p.size as size,
                            h.popularity as popularity
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
						ON p.id = h.presence_id
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
            $activeUserProportion = $this->activeUserProportion[$d['size']] ?
                $this->activeUserProportion[$d['size']] : 1;
            $activeUsers = $d['popularity']*$activeUserProportion;
            $score = ($d['likes'] + $d['comments'] * 4 + $d['share_count'] * 7) / 12;
            $scores[$d['presence_id']] = [
                'popularity' => $d['popularity'],
                'active_users' => $activeUsers,
                'likes' => $d['likes'],
                'comments' => $d['comments'],
                'shares' => $d['share_count'],
                'weighted_engagement' => $score,
                'scaled_engagement' => $score / $activeUsers
            ];
        }
        return $scores;
    }
}