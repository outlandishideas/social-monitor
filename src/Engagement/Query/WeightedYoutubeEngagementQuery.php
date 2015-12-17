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
    private $videoHistoryColumns = ['views','likes','dislikes','comments'];

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

        $changeQuery = "
                    SELECT p.presence_id,SUM(data.change) FROM (
                        SELECT
                        t2.video_id,
                        t2.type,
                        (CASE t2.type
                          WHEN 'views' THEN t2.change
                          WHEN 'likes' THEN t2.change * 2
                          WHEN 'dislikes' THEN t2.change * 2
                          WHEN 'comments' THEN t2.change * 3 END) as `change`
                         FROM
                        (SELECT
					      (last.video_id) as `video_id`,
					      last.type as `type`,
					      (MIN(first.datetime)) as `start`,
					      (MAX(last.datetime)) as `end`
				        FROM
					      (
                            (SELECT * FROM $videoHistory WHERE DATE(datetime) >= :then) AS first
                            INNER JOIN
                            (SELECT * FROM $videoHistory WHERE DATE(datetime) <= :now) AS last
                            ON first.video_id = last.video_id AND first.type = last.type
					      )
                        GROUP BY video_id,type)
                        AS t1
                        INNER JOIN
                        (SELECT
                          last.video_id as `video_id`,
                          last.type as `type`,
                          (last.value - first.value) as `change`,
                          first.datetime as `start`,
                          last.datetime as `end`
                        FROM
                          (
                            (SELECT * FROM $videoHistory) AS first
                            INNER JOIN
                            (SELECT * FROM $videoHistory) AS last
                            ON first.video_id = last.video_id AND first.type = last.type
					      )
					    ) AS t2 ON t1.video_id = t2.video_id AND t1.type = t2.type
					    AND t1.start = t2.start AND t1.end = t2.end
					    ) AS data
					    INNER JOIN
					    (SELECT presence_id,video_id FROM $videoStream) AS p ON data.video_id = p.video_id";

        $statement = $this->db->prepare($changeQuery);
        $success = $statement->execute([
            ':now' => $now->format('Y-m-d'),
            ':then' => $then->format('Y-m-d')
        ]);
        $value = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
        return $value;
    }
}