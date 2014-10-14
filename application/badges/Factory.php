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
			Badge_Total::getName() => 'Badge_Total',
			Badge_Reach::getName() => 'Badge_Reach',
			Badge_Engagement::getName() => 'Badge_Engagement',
			Badge_Quality::getName() => 'Badge_Quality'
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

	public static function getAllCurrentData(Badge_Period $dateRange, $startDate, $endDate, $presenceIds = array()) {
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
			$clauses[] = 'h.presenceId IN ('.implode(',', array_map('intval', $presenceIds)).')';
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
					$row->$badgeType = intval($row->$badgeType);
					$rank = $badgeType . '_rank';
					$row->$rank = intval($row->$rank);
				}
			}
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