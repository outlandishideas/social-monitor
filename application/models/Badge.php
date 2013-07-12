<?php

/**
 * Class Model_Badge
 * @param array $data - the data to calculate this badge from
 * @param string $type - the type of badge this is (Total, Reach, Engagement, Quality)
 * @param string $item - the item (Model_Presence, Model_Campaign) tis badge is for
 * @param int $class - The class that this item belongs to
 */
class Model_Badge {

    public $type;                           //the badge type
    public $title;                          //the title for this badge
    public $score = null;                   //the total score of this bag (out of 100)
    public $rank;                        //the rank of this badge
    public $rankTotal;                   //the total number of presences/groups/countries
    public $class;                          //the model that this badge belongs to
    public $data;                           //the data for the badge
    public $badges;                         //the data for the badge
    public $item;                           //item this badge belongs to
	/** @var $presences Model_Presence[] */
    public $presences = array();            //array of presences (only one for Model_Presence badges)
    public $metrics = array();

    //Badge Metrics
    const BADGE_TYPE_TOTAL = 'total';
    const BADGE_TYPE_REACH = 'reach';
    const BADGE_TYPE_ENGAGEMENT = 'engagement';
    const BADGE_TYPE_QUALITY = 'quality';

	public static $ALL_BADGE_TYPES = array(
		self::BADGE_TYPE_TOTAL,
		self::BADGE_TYPE_REACH,
		self::BADGE_TYPE_ENGAGEMENT,
	    self::BADGE_TYPE_QUALITY
	);

    public static $METRIC_QUALITY = array(
        Model_Presence::METRIC_POSTS_PER_DAY,
        Model_Presence::METRIC_LINKS_PER_DAY,
        Model_Presence::METRIC_LIKES_PER_POST
    );

    public static $METRIC_ENGAGEMENT = array(
        Model_Presence::METRIC_RATIO_REPLIES_TO_OTHERS_POSTS,
        Model_Presence::METRIC_RESPONSE_TIME
    );

    public static $METRIC_REACH = array(
        Model_Presence::METRIC_POPULARITY_PERCENT,
        Model_Presence::METRIC_POPULARITY_TIME
    );

    public function __construct($data, $type, $item, $class)
    {
        $this->type = $type;
        $this->rankType = $this->type.'_rank';
	    $this->title = self::badgeTitle($type);

        $this->class = $class;
        $this->item = $item;

        if($this->class == 'Model_Presence'){
            $this->presences = array($item);
        } else {
            $this->presences = $item->getPresences();
        }

        foreach($this->presences as $presence){
            $presence->name = $presence->handle;
        }

        $this->data = $data;
        $this->getMetrics();

        $this->rankTotal = count($this->data);

        $type = $this->type;
        $rankType = $this->rankType;

        //if this item exists in the data->score array set the score, otherwise set to 0
        if(array_key_exists($item->id, $this->data)){
            $this->score = $this->data[$item->id]->$type;
            $this->rank = $this->data[$item->id]->$rankType;
        }

    }

	public static function badgeTitle($type) {
		switch ($type) {
			case self::BADGE_TYPE_TOTAL:
				return 'Global Score';
			case self::BADGE_TYPE_REACH:
				return 'Reach';
			case self::BADGE_TYPE_ENGAGEMENT:
				return 'Engagement';
			case self::BADGE_TYPE_QUALITY:
				return 'Quality';
		}
		return '';
	}

    private function calculateTotalScores()
    {
        $denominator = count($this->badges);
        $tempBadges = $this->badges;
        $tempBadge = array_pop ($tempBadges);

        $scores = $tempBadge->data->score;

        foreach($tempBadges as $badge){
            foreach($badge->data->score as $id => $score){

                $scores[$id] += $score;

            }
        }

        return array_map(function($a) use ($denominator) {
            return $a/$denominator;
        }, $scores);
    }

    private function getMetrics()
    {
        //for each presence get the values of the metrics and add them onto the Model_Presence object
        foreach($this->presences as $p => $presence){

            $data = $presence->getMetrics($this->type);

            if(count($this->presences) < 2) {

                $this->metrics = $data;
                return;

            } else {

                $this->presences[$p]->metrics = $data;
                foreach($data as $m => $metric){
                    if(!isset($this->metrics[$m])) {
                        $this->metrics[$m] = (object)array(
                            'score' => 0,
                            'type' => $m,
                            'title' => $metric->title
                        );
                    }
                    $this->metrics[$m]->score += $metric->score;
                }

            }

        }

        foreach($this->metrics as $metric){
            $metric->score /= count($this->presences);
        }
    }

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
		$presences = Model_Presence::fetchAll();
		$presenceCount = count($presences);
		$currentDate = clone $startDate;
		while ($currentDate <= $endDate) {
			$dateString = $currentDate->format('Y-m-d');
			if (empty($groupedData[$dateString][$dateRange]) || count($groupedData[$dateString][$dateRange]) < $presenceCount) {
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
			self::BADGE_TYPE_REACH => self::$METRIC_REACH,
			self::BADGE_TYPE_ENGAGEMENT => self::$METRIC_ENGAGEMENT,
			self::BADGE_TYPE_QUALITY => self::$METRIC_QUALITY
		);

		foreach($presences as $presence){

			//$dataRow is an object with four properties: presence_id, type, value, datetime (matching columns in presence_history table)
			$dataRow = (object)array(
				'presence_id' => $presence->id,
				'date' => $date,
				'daterange' => $range
			);

			foreach($badgeMetrics as $badgeType => $metrics){
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

	public static function assignRanks($presenceData, $badgeType) {
		//sorts the data by the current badge score (descending)
		usort($presenceData, function($a, $b) use ($badgeType){
			if($a->$badgeType == $b->$badgeType) return 0;
			return $a->$badgeType > $b->$badgeType ? -1 : 1;
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