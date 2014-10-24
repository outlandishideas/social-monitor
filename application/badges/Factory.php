<?php

abstract class Badge_Factory
{
	protected static $badges = array();

	protected static $db = null;

	protected static function getClassName($name)
	{
		$classNames = self::getClassNames();
		return $classNames[$name];
	}

	public static function getClassNames()
	{
		return array(
			Badge_Reach::getName() => 'Badge_Reach',
			Badge_Engagement::getName() => 'Badge_Engagement',
			Badge_Quality::getName() => 'Badge_Quality',
			Badge_Total::getName() => 'Badge_Total'
		);
	}

	public static function getBadgeNames()
	{
		$classNames = self::getClassNames();
		return array_keys($classNames);
	}

    /**
     * @param $name
     * @return Badge_Abstract
     */
    public static function getBadge($name)
	{
		if (!array_key_exists($name, self::$badges)) {
			$className = static::getClassName($name);
			self::$badges[$name] = new $className(self::getDb());
		}
		return self::$badges[$name];
	}

    /**
     * @return Badge_Abstract[]
     */
    public static function getBadges()
	{
		$badges = array();
		foreach(self::getBadgeNames() as $name){
			$badges[$name] = self::getBadge($name);
		}
		return $badges;
	}

	public static function guaranteeHistoricalData(
		Badge_Period $daterange,
		\DateTime $startDate,
		\DateTime $endDate,
		$presenceIds = array()
	) {
		$data = static::getAllCurrentData($daterange, $startDate, $endDate, $presenceIds);
		if (is_null($data)) {
            $data = array();
        }
		$sorted = array();
		foreach ($data as $row) {
			if (!array_key_exists($row->date, $sorted)) {
				$sorted[$row->date] = array();
			}
			$sorted[$row->date][$row->presence_id] = $row;
		}

		if (count($presenceIds)) {
			$presences = NewModel_PresenceFactory::getPresencesById($presenceIds);
		} else {
			$presences = NewModel_PresenceFactory::getPresences();
		}

		$currentDate = clone $startDate;

        // get all badges, except for total (the total score is not saved)
        $badges = static::getBadges();
        unset($badges[Badge_Total::getName()]);

		while ($currentDate <= $endDate) {
            $formattedDate = $currentDate->format('Y-m-d');
			if (array_key_exists($formattedDate, $sorted)) {
                $badgeScores = $sorted[$formattedDate];
            } else {
                $badgeScores = array();
            }
            // only calculate the scores for missing presences, and only calculate ranks if something new has been calculated
            $missingCount = 0;
            $successCount = 0;
            foreach ($presences as $p) {
                $missing = !array_key_exists($p->getId(), $badgeScores);
                $existing = $missing ? array() : (array)$badgeScores[$p->getId()];
                foreach ($badges as $b) {
                    if ($missing || is_null($existing[$b->getName()])) {
                        $missingCount++;
                        if (!is_null($b->calculate($p, $currentDate, $daterange))) {
                            $successCount++;
                        }
                    }
                }
            }
            if ($successCount > 0) {
                foreach ($badges as $b) {
                    $b->assignRanks($currentDate, $daterange);
                }
            }
			$currentDate->modify('+1 day');
		}
	}

	public static function getAllCurrentData(
		Badge_Period $dateRange,
		\DateTime $startDate,
		\DateTime $endDate,
		$presenceIds = array()
	) {
		$clauses = array(
			'h.date >= :start_date',
			'h.date <= :end_date',
			'h.daterange = :date_range'
		);
		$args = array(
			':start_date' => $startDate->format('Y-m-d'),
			':end_date' => $endDate->format('Y-m-d'),
			':date_range' => (string) $dateRange
		);
		if (count($presenceIds)) {
			$clauses[] = 'h.presence_id IN ('.implode(',', array_map('intval', $presenceIds)).')';
		}

		$sql = '
			SELECT
				h.*,
				c.campaign_id
			FROM
				badge_history as h
				LEFT OUTER JOIN campaign_presences as c ON h.presence_id = c.presence_id
			WHERE
				'.implode(' AND ', $clauses).'
			ORDER BY
				h.presence_id ASC,
				h.date DESC
		';

		$stmt = self::getDb()->prepare($sql);
		$stmt->execute($args);
		$data = $stmt->fetchAll(PDO::FETCH_OBJ);
		if (!$data) {
			return null;
		}
        // convert rank strings to ints
        $badgeNames = self::getBadgeNames();
        $rankNames = array_filter(array_map(
                function($type) {
                    return $type == Badge_Total::getName() ? null : $type . '_rank';
                }, $badgeNames));
		foreach ($data as $row) {
			foreach ($rankNames as $rank) {
                $row->$rank = intval($row->$rank);
			}
		}
		return $data;
	}

	public static function badgesData($asArray = false, $presenceIds = array())
	{
		if(!is_array($presenceIds)) {
            $presenceIds = array($presenceIds);
        }
		$key = 'presence_badges';
		$data = BaseController::getObjectCache($key);
		if (!$data || count((array)$data) < 1) {
			$endDate = new DateTime("now");
			$startDate = clone $endDate;
			for ($i = 0; $i < 5; $i++) {
				// while no count data keep trying further back in the past.
				// only try 5 times, as it is probably a new presence and so has no cached data
				$data = Badge_Factory::getAllCurrentData(Badge_Period::MONTH(), $startDate, $endDate);
				if ($data) {
					break;
				}
				$startDate->modify("-1 day");
				$endDate->modify("-1 day");
			}
			BaseController::setObjectCache($key, $data, true);
		}

		if(!empty($presenceIds)){
			$data = array_filter($data, function($a) use ($presenceIds) {
				return in_array($a->presence_id, $presenceIds);
			});
		}
		if (!is_array($data)) {
			$data = array();
		}
		if($asArray){
			$data = array_map(function($a){
				return (array) $a;
			}, $data);
		}

		return $data;
	}

	public static function setDB(PDO $db)
	{
		self::$db = $db;
	}

    /**
     * @return PDO
     */
    protected static function getDb()
	{
		if (is_null(self::$db)) {
			self::$db = $db = Zend_Registry::get('db')->getConnection();
		}
		return self::$db;
	}
}