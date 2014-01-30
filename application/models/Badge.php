
<?php

class Model_Badge {

    //Badge Metrics
    const BADGE_TYPE_TOTAL = 'total';
    const BADGE_TYPE_REACH = 'reach';
    const BADGE_TYPE_ENGAGEMENT = 'engagement';
    const BADGE_TYPE_QUALITY = 'quality';

    const BADGE_TYPE_TOTAL_DESC = '<p>The Overall Score Badge provides an overall score for how well the presence, country or SBU is doing in the other three badges. This score combines the total scores of the following three badges:</p><ul><li>Reach</li><li>Engagement</li><li>Quality</li></ul>';
    const BADGE_TYPE_REACH_DESC = '<p>The Reach Badge provides an overall score for how well the presence, country or SBU reaches its audience. This score combines the following metrics:</p><ul><li>Current number of Fans / Followers</li><li>Number of months to reach the Target number of Fans / Followers</li><li>Average number of shares / retweets for each post / tweet</li></ul><p>These metrics are compared against their targets, to provide a percentage score, and the average of both metrics is provided as the overall score for the Reach Badge.</p>';
    const BADGE_TYPE_ENGAGEMENT_DESC = '<p>The Engagement Badge provides an overall score for how well the presence, country or SBU engages with its audience. This score combines the following metrics:</p><ul><li>The ratio of replies by the presence owner to the number of posts / tweets by others.</li><li>The average time it takes to reply to a post / tweet.</li><li>The Klout Score for this presence (Twitter Only).</li><li>The Facebook Engagement score for this presence (Facebook Only).</li></ul><p>These metrics are compared against their targets to provide a percentage score, and the average of both metrics is provided as the overall score for the Engagement Badge.</p>';
    const BADGE_TYPE_QUALITY_DESC = '<p>The Quality Badge provides an overall score for the quality of the posts produced by the presence or presences in a Country or SBU. This score combines the following metrics:</p><ul><li>The average number of posts / tweets per day.</li><li>The average number of links per day.</li><li>The average number of likes / retweets per post / tweet.</li><li>The Sign Off status of presence.</li><li>The Branding status of the presence.</li><li>The number of relevant posts made each day.</li></ul><p>These metrics are compared against the targets to provide a percentage score, and the average of all three metrics is provided as the overall score for the Quality Badge.</p>';

//    const METRIC_SIGN_OFF = 'sign_off';
//    const METRIC_BRANDING = 'branding';

	public static $ALL_BADGE_TYPES = array(
		self::BADGE_TYPE_TOTAL,
		self::BADGE_TYPE_REACH,
		self::BADGE_TYPE_ENGAGEMENT,
	    self::BADGE_TYPE_QUALITY
	);

	private static $metricsCache = array();

    public static function metrics($type) {
	    if (array_key_exists($type, self::$metricsCache)) {
		    return self::$metricsCache[$type];
	    }

        $metrics = array();
        switch ($type) {
            case self::BADGE_TYPE_QUALITY:
                $metrics[Model_Presence::METRIC_POSTS_PER_DAY] = 1;
                $metrics[Model_Presence::METRIC_LINKS_PER_DAY] = 1;
                $metrics[Model_Presence::METRIC_LIKES_PER_POST] = 1;
                $metrics[Model_Presence::METRIC_SIGN_OFF] = 1;
                $metrics[Model_Presence::METRIC_BRANDING] = 1;
                $metrics[Model_Presence::METRIC_RELEVANCE] = 1;
                break;
            case self::BADGE_TYPE_ENGAGEMENT:
                $metrics[Model_Presence::METRIC_RATIO_REPLIES_TO_OTHERS_POSTS] = 1;
                $metrics[Model_Presence::METRIC_RESPONSE_TIME] = 1;
                $metrics[Model_Presence::METRIC_KLOUT] = 1;
                $metrics[Model_Presence::METRIC_FB_ENGAGEMENT] = 1;
                break;
            case self::BADGE_TYPE_REACH:
                $metrics[Model_Presence::METRIC_POPULARITY_PERCENT] =  1;
                $metrics[Model_Presence::METRIC_POPULARITY_TIME] =  1;
                $metrics[Model_Presence::METRIC_SHARING] =  1;
                break;
        }

        foreach ($metrics as $name=>$weight) {
            //get weight from database, if it exists
	        $weighting = BaseController::getOption($name . '_weighting');
	        if ($weighting > 0) {
	            $metrics[$name] = $weighting;
	        }
        }
	    self::$metricsCache[$type] = $metrics;
        return $metrics;
    }

	public static function badgeTitle($type) {
		switch ($type) {
			case self::BADGE_TYPE_TOTAL:
				return 'Overall Score';
			case self::BADGE_TYPE_REACH:
				return 'Reach';
			case self::BADGE_TYPE_ENGAGEMENT:
				return 'Engagement';
			case self::BADGE_TYPE_QUALITY:
				return 'Quality';
		}
		return '';
	}

	/**
	 * @param $dateRange string
	 * @param $date DateTime
	 * @param $presenceIds int[]
	 * @return array
	 */
//	public static function getData($dateRange, $date, $presenceIds) {
//		$clauses = array(
//			'date = :start_date',
//			'daterange = :date_range',
//			'presence_id IN (' . implode(',', $presenceIds) . ')'
//		);
//		$args = array(
//			':start_date' => $date->format('Y-m-d'),
//			':date_range' => $dateRange
//		);
//
//		$sql =
//			'SELECT *
//			FROM badge_history
//			WHERE '.implode(' AND ', $clauses).'
//            ORDER BY
//                presence_id ASC,
//                date DESC';
//
//		$stmt = BaseController::db()->prepare($sql);
//		$stmt->execute($args);
//		$data = $stmt->fetchAll(PDO::FETCH_OBJ);
//
//		$indexedData = array();
//		foreach ($data as $row) {
//			$indexedData[$row->presence_id] = $row;
//		}
//
//		return $indexedData;
//	}

	/**
	 * Fetches all of the historical data using the given date range, between the given dates
	 * @param $dateRange string 'week' or 'month'
	 * @param $startDate DateTime
	 * @param $endDate DateTime
	 * @return array
	 */
	public static function getAllData($dateRange, $startDate, $endDate) {
		$clauses = array(
			'h.date >= :start_date',
			'h.date <= :end_date',
			'h.daterange = :date_range'
		);
		$args = array(
			':start_date' => $startDate->format('Y-m-d'),
			':end_date' => $endDate->format('Y-m-d'),
			':date_range' => $dateRange
		);

		$sql =
			'SELECT h.*, c.campaign_id
			FROM badge_history as h
			LEFT OUTER JOIN campaign_presences as c
				ON h.presence_id = c.presence_id
			WHERE '.implode(' AND ', $clauses).'
            ORDER BY
                h.presence_id ASC,
                h.date DESC';

		$stmt = BaseController::db()->prepare($sql);
		$stmt->execute($args);
		$data = $stmt->fetchAll(PDO::FETCH_OBJ);

		// group the data by date and range, just to check that everything is present
		$groupedData = array();
		foreach ($data as $row) {
			if (!isset($groupedData[$row->date])) {
				$groupedData[$row->date] = array();
			}
			if (!isset($groupedData[$row->date][$row->daterange])) {
				$groupedData[$row->date][$row->daterange] = array();
			}
			$groupedData[$row->date][$row->daterange][] = $row;
		}

		$fetchAgain = false;
		/** @var Model_Presence[] $presences */
		$presences = null;
		$presenceCount = Model_Presence::countAll();
		$currentDate = clone $startDate;
		while ($currentDate <= $endDate) {
			$dateString = $currentDate->format('Y-m-d');
			if (empty($groupedData[$dateString][$dateRange]) || count($groupedData[$dateString][$dateRange]) < $presenceCount) {
				if (!$presences) {
					$presences = Model_Presence::fetchAll();
				}
				self::populateBadgeHistory($presences, $dateString, $dateRange);
				$fetchAgain = true;
			}
			$currentDate = $currentDate->add(DateInterval::createFromDateString('1 day'));
		}

		if($fetchAgain){
			$stmt->execute($args);
			$data = $stmt->fetchAll(PDO::FETCH_OBJ);
		}

		// convert all the badge scores and ranks from strings to integers
		foreach ($data as $row) {
			foreach ($row as $key=>$value) {
				if (strpos($key, '_rank') > 0 || in_array($key, self::$ALL_BADGE_TYPES)) {
					$row->$key = intval($value);
				}
			}
		}

		return $data;
	}

	/**
	 * @param $presences Model_Presence[]
	 * @param $date string
	 * @param $range string
	 * @return array
	 */
	public static function populateBadgeHistory($presences, $date, $range){
		$data = array();

		//foreach presence and foreach badge (not total badge), calculate the metrics
		$badgeMetrics = array(
			self::BADGE_TYPE_REACH => self::metrics(self::BADGE_TYPE_REACH),
			self::BADGE_TYPE_ENGAGEMENT => self::metrics(self::BADGE_TYPE_ENGAGEMENT),
			self::BADGE_TYPE_QUALITY => self::metrics(self::BADGE_TYPE_QUALITY)
		);

		foreach($presences as $presence){

			//$dataRow is an object with four properties: presence_id, type, value, datetime (matching columns in presence_history table)
			$dataRow = (object)array(
				'presence_id' => $presence->id,
				'date' => $date,
				'daterange' => $range
			);

			foreach($badgeMetrics as $badgeType => $metrics){

                if($presence->isForFacebook() && $badgeType == self::BADGE_TYPE_ENGAGEMENT) unset($metrics[Model_Presence::METRIC_KLOUT]);
                if(!$presence->isForFacebook() && $badgeType == self::BADGE_TYPE_ENGAGEMENT) unset($metrics[Model_Presence::METRIC_FB_ENGAGEMENT]);
                $dataRow->$badgeType = $presence->getMetricsScore($date, $metrics, $range);
			}

			$data[] = $dataRow;
		}

		//foreach badge (not total), sort the data and then rank it
		foreach(Model_Badge::$ALL_BADGE_TYPES as $badgeType){
			if($badgeType != Model_Badge::BADGE_TYPE_TOTAL) {
				self::assignRanks($data, $badgeType);
			}
		}

		//insert the newly calculated data back into the presence_history table, so next time its ready for us.
		usort($data, function($a, $b) {
			return $a->presence_id > $b->presence_id ? 1 : -1;
		});
		$setHistoryArgs = array_map(function($a) { return (array)$a; }, $data);
		Model_Base::insertData('badge_history', $setHistoryArgs);

		return $data;
	}

	public static function calculateTotalScore($badgeData) {
		$badgeTypes = self::$ALL_BADGE_TYPES;
		$total = 0;
		foreach($badgeTypes as $type){
			if($type != self::BADGE_TYPE_TOTAL) {
				$total += $badgeData->$type;
			}
		}
		$badgeData->total = $total/(count($badgeTypes)-1);
	}

	public static function assignRanks($presenceData, $badgeType) {
		//sorts the data by the current badge score (descending)
		usort($presenceData, function($a, $b) use ($badgeType){
			$aVal = $a->$badgeType;
			$bVal = $b->$badgeType;
			if($aVal == $bVal) return 0;
			return $aVal > $bVal ? -1 : 1;
		});

		//foreach row (ordered by score of the current badge type) set the ranking
		$lastScore = null;
		$lastRank = null;
		foreach($presenceData as $i=>$row) {
			if ($row->$badgeType == $lastScore){
				$rank = $lastRank;
			} else {
				$rank = $i+1;
			}

			$row->{$badgeType.'_rank'} = $rank;

			$lastScore = $row->$badgeType;
			$lastRank = $rank;
		}
	}
}