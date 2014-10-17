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

	public static function getBadge($name)
	{
		if (!array_key_exists($name, self::$badges)) {
			$className = static::getClassName($name);
			self::$badges[$name] = new $className(self::getDb());
		}
		return self::$badges[$name];
	}

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
		if (is_null($data)) $data = array();
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
		while ($currentDate <= $endDate) {
			if (!array_key_exists($currentDate->format('Y-m-d'), $sorted)) {
				foreach ($presences as $p) {
					foreach (static::getBadges() as $b) {
						if ($b->getName() == Badge_Total::getName()) {
							continue;
						}
						$b->calculate($p, $currentDate, $daterange);
					}
					static::getBadge(Badge_Total::getName())->calculate($p, $currentDate, $daterange);
				}
				foreach (static::getBadges() as $b) {
					$b->assignRanks($currentDate, $daterange);
				}
			} else {
				$doRanking = false;
				foreach ($presences as $p) {
					if (!array_key_exists($p->getId(), $sorted[$currentDate->format('Y-m-d')])) {
						foreach (static::getBadges() as $b) {
							if ($b->getName() == Badge_Total::getName()) {
								continue;
							}
							$b->calculate($p, $currentDate, $daterange);
						}
						static::getBadge(Badge_Total::getName())->calculate($p, $currentDate, $daterange);
						$doRanking = true;
					}
				}
				if ($doRanking) {
					foreach (static::getBadges() as $b) {
						$b->assignRanks($currentDate, $daterange);
					}
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
		foreach ($data as $row) {
			foreach (self::getBadgeNames() as $badgeType) {
				if ($badgeType != Badge_Total::getName()) {
					//$row->$badgeType = intval($row->$badgeType);
					$rank = $badgeType . '_rank';
					$row->$rank = intval($row->$rank);
				}
			}
		}
		return $data;
	}

	public static function badgesData($asArray = false, $presenceIds = array())
	{
		if(!is_array($presenceIds)) $presenceIds = array($presenceIds);
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
			$data = array_filter($data, function($a) use($presenceIds) {
				if(in_array($a->presence_id, $presenceIds)){
					return true;
				}
				return false;
			});
		}
		if($asArray){
			$data = array_map(function($a){
				return (array)$a;
			}, $data);
		}

		return $data;
	}

	public static function setDB(PDO $db)
	{
		self::$db = $db;
	}

	protected static function getDb()
	{
		if (is_null(self::$db)) {
			self::$db = $db = Zend_Registry::get('db')->getConnection();
		}
		return self::$db;
	}
}