<?php

namespace Outlandish\SocialMonitor\Engagement\Query;


use BaseController;
use DateTime;
use Outlandish\SocialMonitor\Database\Database;

class WeightedYoutubeEngagementQuery extends Query
{
    const VIDEO_HISTORY_TABLE = 'youtube_video_history';
    const VIDEO_STREAM_TABLE = 'youtube_video_stream';
    const PRESENCE_STREAM_TABLE = 'presence_history';
    const PRESENCE_TABLE = 'presences';
    private $typeWeightMap = ['views' => 0, 'likes' => 1,'dislikes' => 1,'comments' => 4,'subscriptions'=>50];

    public function __construct(Database $db)
    {
		parent::__construct($db);
        $this->activeUserProportion = array();
        $this->activeUserProportion[0] = BaseController::getOption('yt_active_user_percentage_small') / 100;
        $this->activeUserProportion[1] = BaseController::getOption('yt_active_user_percentage_medium') / 100;
        $this->activeUserProportion[2] = BaseController::getOption('yt_active_user_percentage_large') / 100;
        $this->activeUserProportion[3] = BaseController::getOption('yt_active_user_percentage_large') / 100;
    }

    public function fetch(DateTime $now, DateTime $then)
    {
        $rows = $this->getData($now, $then);

        $scores = [];

        foreach ($rows as $row) {
            $scores[$row['presence_id']] = $row['engagement'];
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

        $nowStr = $now->format('Y-m-d');
        $thenStr = $then->format('Y-m-d');

        $videoHistory = self::VIDEO_HISTORY_TABLE;
        $videoStream = self::VIDEO_STREAM_TABLE;
        $presenceHistory = self::PRESENCE_STREAM_TABLE;
        $presenceTable = self::PRESENCE_TABLE;
        $popularity = 'popularity';

        // this array stores the data for each presence
        $allPresencesEngagement = array();

        /*
         * First find the presence ids for youtube presences
         */
        $presenceIdQuery = "SELECT id,size FROM $presenceTable WHERE type='youtube'";
        $statement = $this->db->prepare($presenceIdQuery);
        $statement->execute();
        $presenceSizes = $statement->fetchAll(\PDO::FETCH_KEY_PAIR);

        foreach($presenceSizes as $presenceId=>$size) {

            // this stores the values of different engagement types for the presence
            $presenceData = ["presence_id"=>$presenceId];

            /*
             * Find the change in subscribers for this presence - as increased subscribers is an engagement
             */
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
            $presencePopularity = $statement->fetchAll(\PDO::FETCH_COLUMN);
            $presencePopularity = array_key_exists(0,$presencePopularity) ? $presencePopularity[0] : 0;

            $statement->execute([
                ':now' => $thenStr
            ]);
            $prevPopularity = $statement->fetchAll(\PDO::FETCH_COLUMN);
            $prevPopularity = array_key_exists(0,$prevPopularity) ? $prevPopularity[0] : $presencePopularity;

            $presenceData['popularity'] = intval($presencePopularity,10);
            $activeUserProportion = $this->activeUserProportion[$size] ? $this->activeUserProportion[$size] : 1;
            $presenceData['active_users'] = $presenceData['popularity']*$activeUserProportion;

            /**
             * Get the total views,likes,dislikes,comments on all videos from this presence at time $now.
             * Then get the same for time $then. The difference between the totals gives us the engagement
             * over the time period $then -> $now.
             */
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
                  WHERE DATE(datetime) = :date)
                  AS `history`
                  ON stream.id = history.video_id
                GROUP BY history.type";

            $statement = $this->db->prepare($videoHistoryQuery);
            $statement->execute([
                ':date' => $nowStr
            ]);

            $videoEndValues = $statement->fetchAll(\PDO::FETCH_KEY_PAIR);

            $statement = $this->db->prepare($videoHistoryQuery);
            $statement->execute([
                ':date' => $thenStr
            ]);

            $videoStartValues = $statement->fetchAll(\PDO::FETCH_KEY_PAIR);

            // in this loop we calculate the change in each type of engagement and save in $presenceEngagementMap
            foreach(array_keys($this->typeWeightMap) as $type) {
                if($type === 'subscriptions') {
                    $change = $presencePopularity - $prevPopularity;
                } else {
                    $endValue = array_key_exists($type, $videoEndValues) ? $videoEndValues[$type] : 0;
                    $startValue = array_key_exists($type, $videoStartValues) ? $videoStartValues[$type] : 0;
                    $change = $endValue - $startValue;
                }
                $presenceData[$type] = $change;
            }

            // here we calculate the weighted engagement by doing a weighted sum of the different types
            $weightedEngagement = 0;
            foreach($this->typeWeightMap as $type=>$weight) {
                $weightedEngagement += $presenceData[$type]*$weight;
            }
                // we then scale by the views
            $scale = $presenceData['views'] ? $presenceData['views'] : 1;

            $presenceData['likes_equivalent'] = $weightedEngagement;
            $presenceData['engagement'] = $weightedEngagement / $scale;

            $allPresencesEngagement[] = $presenceData;
        }
        return $allPresencesEngagement;
    }
}