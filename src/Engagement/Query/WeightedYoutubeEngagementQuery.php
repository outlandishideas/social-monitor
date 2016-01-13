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
    const PRESENCE_TABLE = 'presences';
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

        $nowStr = $now->format('Y-m-d');
        $thenStr = $then->format('Y-m-d');

        $videoHistory = self::VIDEO_HISTORY_TABLE;
        $videoStream = self::VIDEO_STREAM_TABLE;
        $presenceHistory = self::PRESENCE_STREAM_TABLE;
        $presenceTable = self::PRESENCE_TABLE;
        $popularity = 'popularity';
        $presenceEngagementMap = array();

        $presenceIdQuery = "SELECT id FROM $presenceTable WHERE type='youtube'";
        $statement = $this->db->prepare($presenceIdQuery);
        $statement->execute();
        $presenceIds = $statement->fetchAll(PDO::FETCH_COLUMN);

        foreach($presenceIds as $presenceId) {
            $presencePopularityQuery = "
            SELECT value
              FROM $presenceHistory
              WHERE presence_id=$presenceId
                AND type='$popularity'
                AND DATE(datetime) = :now
                ORDER BY datetime
                LIMIT 1";

            $statement = $this->db->prepare($presencePopularityQuery);
            $statement->execute([
                ':now' => $nowStr
            ]);

            $presencePopularity = $statement->fetchAll(PDO::FETCH_COLUMN);

            $presencePopularity = array_key_exists(0,$presencePopularity) ? $presencePopularity[0] : 0;

            $presenceEngagementMap[$presenceId] = ['popularity'=>intval($presencePopularity,10)];

            $videoHistoryQuery = "
                SELECT history.type,SUM(history.value)
                FROM (
                  SELECT id
                  FROM $videoStream
                  WHERE presence_id = $presenceId)
                  AS `stream`
                  INNER JOIN (
                  SELECT video_id,type,value
                  FROM $videoHistory
                  WHERE DATE(datetime) = :now)
                  AS `history`
                  ON stream.id = history.video_id
                GROUP BY history.type";

            $statement = $this->db->prepare($videoHistoryQuery);
            $statement->execute([
                ':now' => $nowStr
            ]);

            $videoEndValues = $statement->fetchAll(PDO::FETCH_KEY_PAIR);

            $statement = $this->db->prepare($videoHistoryQuery);
            $statement->execute([
                ':now' => $thenStr
            ]);

            $videoStartValues = $statement->fetchAll(PDO::FETCH_KEY_PAIR);

            foreach(array_keys($this->typeWeightMap) as $type) {
                $endValue = array_key_exists($type,$videoEndValues) ? $videoEndValues[$type] : 0;
                $startValue = array_key_exists($type,$videoStartValues) ? $videoStartValues[$type] : 0;
                $change = $endValue - $startValue;
                $presenceEngagementMap[$presenceId][$type] = $change;
            }

            $weightedTotalEngagement = 0;
            foreach($this->typeWeightMap as $type=>$weight) {
                $weightedTotalEngagement += $presenceEngagementMap[$presenceId][$type]*$weight;
            }
            $presenceEngagementMap[$presenceId]['weighted_engagement'] = $weightedTotalEngagement;
        }
        return $presenceEngagementMap;
    }
}