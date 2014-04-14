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

	public static $BADGE_DESCRIPTIONS = array(
		self::BADGE_TYPE_TOTAL => self::BADGE_TYPE_TOTAL_DESC,
		self::BADGE_TYPE_REACH => self::BADGE_TYPE_REACH_DESC,
		self::BADGE_TYPE_ENGAGEMENT => self::BADGE_TYPE_ENGAGEMENT_DESC,
	    self::BADGE_TYPE_QUALITY => self::BADGE_TYPE_QUALITY_DESC
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
    public static function getAllCurrentData($dateRange, $startDate, $endDate) {
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
	    foreach ($data as $row) {
		    foreach (self::$ALL_BADGE_TYPES as $badgeType) {
			    if ($badgeType != self::BADGE_TYPE_TOTAL) {
				    $row->$badgeType = intval($row->$badgeType);
				    $rank = $badgeType . '_rank';
				    $row->$rank = intval($row->$rank);
			    }
		    }
	    }
	    return $data;
    }

	/**
	 * Fetches all of the historical data using the given date range, between the given dates,
	 * and populates any that should be there but isn't
	 * @param $dateRange string 'week' or 'month'
	 * @param $startDate DateTime
	 * @param $endDate DateTime
	 * @return array
	 */
	public static function populateHistoricalData($dateRange, $startDate, $endDate) {
		$data = self::getAllCurrentData($dateRange, $startDate, $endDate);

		// group the data by date and range, just to check that everything is present
		$groupedData = array();
		foreach ($data as $row) {
			if (!isset($groupedData[$row->date])) {
				$groupedData[$row->date] = array();
			}
			if (!isset($groupedData[$row->date][$row->daterange])) {
				$groupedData[$row->date][$row->daterange] = array();
			}
			unset($row->campaign_id);
			$groupedData[$row->date][$row->daterange][$row->presence_id] = $row;
		}
		unset($data);

		$presences = array();
		foreach (Model_Presence::fetchAll() as $p) {
			$presences[$p->id] = $p;
		}
		ksort($presences);
		Model_Presence::populateOwners($presences);

		$currentDate = clone $startDate;
		while ($currentDate <= $endDate) {
			$dateString = $currentDate->format('Y-m-d');
			$toUpdate = $presences;
			$missingRank = false;
			if (!empty($groupedData[$dateString][$dateRange])) {
				$existing = $groupedData[$dateString][$dateRange];
				foreach ($groupedData[$dateString][$dateRange] as $id=>$fields) {
					unset($toUpdate[$id]);
					if (!$missingRank) {
						foreach ($fields as $name=>$value) {
							if (substr($name, -5) == '_rank' && $value == 0) {
								$missingRank = true;
								break;
							}
						}
					}
				}
				unset($groupedData[$dateString][$dateRange]);
				if (empty($groupedData[$dateString])) {
					unset($groupedData[$dateString]);
				}
			} else {
				$existing = array();
			}
			if ($toUpdate || $missingRank) {
				self::populateBadgeHistory($existing, $toUpdate, $dateString, $dateRange);
			}
			$currentDate = $currentDate->add(DateInterval::createFromDateString('1 day'));
		}
	}

	/**
	 * @param $data array
	 * @param $presences Model_Presence[]
	 * @param $date string
	 * @param $range string
	 */
	private static function populateBadgeHistory($data, $presences, $date, $range){

		//foreach presence and foreach badge (not total badge), calculate the metrics
		$badgeMetrics = array();
		foreach (self::$ALL_BADGE_TYPES as $badgeType) {
			if ($badgeType != self::BADGE_TYPE_TOTAL) {
				$badgeMetrics[$badgeType] = self::metrics($badgeType);
			}
		}

		foreach ($data as $row) {
			foreach ($badgeMetrics as $badgeType=>$ignored) {
				$row->$badgeType = floatval($row->$badgeType);
			}
			unset($row->id);
		}

		$currentBatch = array();
		foreach($presences as $presence){
			$dataRow = array(
				'presence_id' => $presence->id,
				'date' => $date,
				'daterange' => $range
			);

			foreach($badgeMetrics as $badgeType => $metrics){
				if ($badgeType == self::BADGE_TYPE_ENGAGEMENT) {
	                if($presence->isForFacebook()) {
		                unset($metrics[Model_Presence::METRIC_KLOUT]);
	                } else {
	                    unset($metrics[Model_Presence::METRIC_FB_ENGAGEMENT]);
		            }
				}
				$dataRow[$badgeType] = $presence->getMetricsScore($date, $metrics, $range);
				$dataRow[$badgeType . '_rank'] = 0;
			}

			$currentBatch[] = $dataRow;
			if (count($currentBatch) >= 25) {
				Model_Base::insertData('badge_history', $currentBatch);
				$currentBatch = array();
			}
			$data[$presence->id] = (object)$dataRow;
		}
		if ($currentBatch) {
			Model_Base::insertData('badge_history', $currentBatch);
		}

		//foreach calculated badge, sort the data and then rank it
		foreach($badgeMetrics as $badgeType => $metrics){
			self::assignRanks($data, $badgeType);
		}

		//insert the newly calculated data back into the presence_history table, so next time its ready for us.
		ksort($data);
		$data = array_map(function($a) { return (array)$a; }, $data);
		Model_Base::insertData('badge_history', $data);
	}

	public static function calculateTotalScoresAndRanks($data) {
		$keyedData = array();
		foreach ($data as $row) {
			if (!isset($keyedData[$row->presence_id])) {
				Model_Badge::calculateTotalScore($row);
				$keyedData[$row->presence_id] = $row;
			}
		}
		Model_Badge::assignRanks($keyedData, 'total');
		$obj = new stdClass();
		foreach ($keyedData as $key=>$value) {
			$obj->$key = $value;
		}
		return $obj;
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

	/**
	 * gets the badges data based on the last month, using the data in the cache (if available)
	 * @param bool $asArray determines the return type
	 * @return array|stdClass
	 */
	public static function badgesData($asArray = false){
		$key = 'presence_badges';
		$data = BaseController::getObjectCache($key);
		if (!$data) {
			$endDate = new DateTime("now");
			$startDate = clone $endDate;
			for ($i=0; $i<5; $i++) {
				// while no count data keep trying further back in the past.
				// only try 5 times, as it is probably a new presence and so has no cached data
				$data = Model_Badge::getAllCurrentData('month', $startDate, $endDate);
				if ($data) {
					break;
				}
				$startDate->modify("-1 day");
				$endDate->modify("-1 day");
			}
			$data = self::calculateTotalScoresAndRanks($data);
			BaseController::setObjectCache($key, $data, true);
		}

		if ($asArray) {
			$tmp = array();
			foreach($data as $key=>$value){
				$tmp[$key] = $value;
			}
			$data = $tmp;
		}

		return $data;
	}
}