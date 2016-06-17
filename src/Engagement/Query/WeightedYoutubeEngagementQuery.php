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

        /*
         * Get the id, size and current popularity of all youtube presences
         */
		$presencesQuery = "
			SELECT presence.id, presence.size, MAX(history.value) AS popularity
 			FROM $presenceTable AS presence LEFT OUTER JOIN (
 				SELECT * FROM $presenceHistory 
				WHERE type = 'popularity' 
				AND DATE(datetime) = :date
			) AS history ON presence.id = history.presence_id
 			WHERE presence.type = 'youtube' 
 			GROUP BY presence.id
 			ORDER BY presence.id ASC, history.datetime ASC";
		$presencesStatement = $this->db->prepare($presencesQuery);

		$presencesStatement->execute([ ':date' => $nowStr ]);
		$currentPopularity = $presencesStatement->fetchAll(\PDO::FETCH_ASSOC);

		$presenceData = array();

		foreach($currentPopularity as $row) {
			$currentPresence = new \stdClass();
			$currentPresence->id = $row['id'];
			$currentPresence->size = $row['size'];
			$currentPresence->proportion = $this->activeUserProportion[$row['size']] ? $this->activeUserProportion[$row['size']] : 1;
			$currentPresence->currentPopularity = intval($row['popularity'], 10);
			$currentPresence->previousPopularity = $currentPresence->currentPopularity;
			$currentPresence->startValues = array();
			$currentPresence->endValues = array();
			foreach ($this->typeWeightMap as $key=>$weight) {
				$currentPresence->startValues[$key] = 0;
				$currentPresence->endValues[$key] = 0;
			}
			$presenceData[$row['id']] = $currentPresence;
		}

		// use same query to get popularity from 1 week ago
		$presencesStatement->execute([ ':date' => $thenStr ]);
		$prevPopularity = $presencesStatement->fetchAll(\PDO::FETCH_ASSOC);
		foreach ($prevPopularity as $row) {
			if ($row['popularity']) {
				$presenceData[$row['id']]->previousPopularity = intval($row['popularity'], 10);
			}
		}


		/**
		 * Get the total views,likes,dislikes,comments on all videos from this presence at time $now.
		 * Then get the same for time $then. The difference between the totals gives us the engagement
		 * over the time period $then -> $now.
		 */
		$videoHistoryQuery = "
				SELECT stream.presence_id, history.type, SUM(history.value) AS value
				FROM $videoStream AS stream INNER JOIN $videoHistory as history 
					ON stream.id = history.video_id
			    WHERE DATE(datetime) = :date
			    GROUP BY stream.presence_id, history.type";
		$videoHistoryStatement = $this->db->prepare($videoHistoryQuery);

		$videoHistoryStatement->execute([ ':date' => $nowStr ]);
		$videoEndValues = $videoHistoryStatement->fetchAll(\PDO::FETCH_ASSOC);
		foreach ($videoEndValues as $row) {
			$presenceData[$row['presence_id']]->endValues[$row['type']] = $row['value'];
		}

		$videoHistoryStatement->execute([ ':date' => $thenStr ]);
		$videoStartValues = $videoHistoryStatement->fetchAll(\PDO::FETCH_ASSOC);
		foreach ($videoStartValues as $row) {
			$presenceData[$row['presence_id']]->startValues[$row['type']] = $row['value'];
		}


		// this array stores the data for each presence
		$allPresencesEngagement = array();
		foreach ($presenceData as $currentPresence) {
			$presenceEngagement = [
				'presence_id' => $currentPresence->id,
	            'popularity' => $currentPresence->currentPopularity,
            	'active_users' => $currentPresence->currentPopularity*$currentPresence->proportion
			];

			// here we calculate the weighted engagement by doing a weighted sum of the different types
			$weightedEngagement = 0;
			foreach($this->typeWeightMap as $type=>$weight) {
				if($type === 'subscriptions') {
					$change = $currentPresence->currentPopularity - $currentPresence->previousPopularity;
				} else {
					$change = $currentPresence->endValues[$type] - $currentPresence->startValues[$type];
				}
				$presenceEngagement[$type] = $change;
				$weightedEngagement += $change*$weight;
			}

			// we then scale by the views
			$scale = $presenceEngagement['views'] ? $presenceEngagement['views'] : 1;

			$presenceEngagement['likes_equivalent'] = $weightedEngagement;
			$presenceEngagement['engagement'] = $weightedEngagement / $scale;

			$allPresencesEngagement[] = $presenceEngagement;
		}

        return $allPresencesEngagement;
    }
}