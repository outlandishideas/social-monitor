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

class WeightedYoutubeEngagementQuery implements Query
{
    const VIDEO_HISTORY_TABLE = 'youtube_video_history';
    const VIDEO_STREAM_TABLE = 'youtube_video_stream';
    const PRESENCE_STREAM_TABLE = 'presence_history';
    private $typeWeightMap = ['views' => 1,'likes' => 4,'dislikes' => 4,'comments' => 7];

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
        $videoHistory = self::VIDEO_HISTORY_TABLE;
        $videoStream = self::VIDEO_STREAM_TABLE;
        $presenceHistory = self::PRESENCE_STREAM_TABLE;
        $popularity = 'popularity';
        $presenceEngagementMap = array();

        /**
         * This query returns the popularity we stored for each presence at time $now (or as close to $now as we can
         * get).
         *
         * It does this using two sub-queries joined together: the first gets the date closest to $now that data
         * exists for, the second gets the values we have between $now and $then, and the join means we get just the
         * value for the date closest to $now.
         *
         * @var $presencePopularityQuery */
        $presencePopularityQuery = "
            SELECT t1.presence_id,
                   t2.value
            FROM
              (SELECT presence_id,
                      (MAX(datetime)) AS `datetime`
              FROM $presenceHistory
              WHERE DATE(datetime) <= :now
                AND DATE(datetime) >= :then
              GROUP BY presence_id) AS t1
            INNER JOIN
              (SELECT presence_id,
                      value,
                      datetime
              FROM $presenceHistory
              WHERE type='$popularity'
                AND DATE(datetime) <= :now
                AND DATE(datetime) >= :then) AS t2
            ON t1.presence_id = t2.presence_id AND t1.datetime = t2.datetime";

        $statement = $this->db->prepare($presencePopularityQuery);
        $statement->execute([
            ':now' => $now->format('Y-m-d'),
            ':then' => $then->format('Y-m-d')
        ]);
        $presencePopularityMap = $statement->fetchAll(PDO::FETCH_KEY_PAIR);

        $videoHistoryQuery = "
        SELECT video_id,type,value
        FROM $videoHistory
        WHERE DATE(datetime) = :now
        ORDER BY video_id";

        $statement = $this->db->prepare($videoHistoryQuery);
        $statement->execute([
            ':now' => $now->format('Y-m-d')
        ]);
        $videoEndValues = $statement->fetchAll(PDO::FETCH_ASSOC);

        $videoStartQuery = "
        SELECT value
        FROM $videoHistory
        WHERE DATE(datetime) = :then
        AND video_id = :video_id
        AND type = :type
        ";
        $statement = $this->db->prepare($videoStartQuery);

        $presenceIdQuery = "
        SELECT presence_id
        FROM $videoStream
        WHERE id = :id";

        $presenceIdStatement = $this->db->prepare($presenceIdQuery);

        foreach($videoEndValues as $videoEndValue) {
            $type = $videoEndValue['type'];
            $statement->execute([
                ':then' => $then->format('Y-m-d'),
                ':type' => $videoEndValue['type'],
                ':video_id' => $videoEndValue['video_id']
            ]);
            $videoStartValue = $statement->fetchColumn();
            $videoStartValue = $videoStartValue ? $videoStartValue : 0;
            $change = $videoEndValue['value'] - $videoStartValue;

            $presenceIdStatement->execute([
                ':id' => $videoEndValue['video_id']
            ]);
            $presenceId = $presenceIdStatement->fetchColumn();
            if(array_key_exists($presenceId,$presenceEngagementMap)) {
                $presenceEngagementMap[$presenceId] += $change * $this->typeWeightMap[$type];
            } else {
                $presenceEngagementMap[$presenceId] = $change * $this->typeWeightMap[$type];
            }
        }

        foreach($presenceEngagementMap as $presenceId => &$value) {
            $scale = array_key_exists($presenceId, $presencePopularityMap) ? $presencePopularityMap[$presenceId] : 1;
            $value = round($value / ($scale));
        }
        return $presenceEngagementMap;
    }
}