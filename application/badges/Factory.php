<?php

use Outlandish\SocialMonitor\Database\Database;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Badge_Factory
{
	protected static $badges = array();
	/** @var \Symfony\Component\DependencyInjection\ContainerInterface */
	protected static $container = null;

    /** @var \Outlandish\SocialMonitor\Database\Database */
	protected static $db = null;

	public static function setContainer($container)
	{
		self::$container = $container;
	}
	
	public static function getBadgeNames()
	{
		return array_keys(self::getBadges());
	}

    /**
     * @param $name
     * @throws Exception
     * @return Badge_Abstract
     */
    public static function getBadge($name)
	{
		$badges = self::getBadges();
		if (!array_key_exists($name, $badges)) {
            throw new Exception('Invalid badge name: ' . $name);
		}
		return $badges[$name];
	}

    /**
     * @return Badge_Abstract[]
     */
    public static function getBadges()
	{
        if (empty(self::$badges)) {
            /** @var Badge_Abstract[] $badges */
            $badges = array(
				self::$container->get('badge.reach'),
				self::$container->get('badge.engagement'),
				self::$container->get('badge.quality'),
				self::$container->get('badge.total')
            );
            self::$badges = array();
            foreach ($badges as $b) {
                self::$badges[$b->getName()] = $b;
            }
        }
        return self::$badges;
	}

	/**
	 * @param Enum_Period $dateRange
	 * @param DateTime $startDate
	 * @param DateTime $endDate
	 * @param OutputInterface $output
	 * @param array $presenceIds
	 * @param bool|false $force
	 */
	public static function guaranteeHistoricalData(
		Enum_Period $dateRange,
		\DateTime $startDate,
		\DateTime $endDate,
        $output = null,
		$presenceIds = array(),
        $force = false
	) {
        if (!$output) {
			$output = new \Symfony\Component\Console\Output\NullOutput();
        }

		$data = static::getAllCurrentData($dateRange, $startDate, $endDate, $presenceIds);
		if (is_null($data)) {
            $data = array();
        }

        // group data by date, then by presence
		$groupedData = array();
		foreach ($data as $row) {
			if (!array_key_exists($row->date, $groupedData)) {
				$groupedData[$row->date] = array();
			}
			$groupedData[$row->date][$row->presence_id] = $row;
		}

		if (count($presenceIds)) {
			$presences = Model_PresenceFactory::getPresencesById($presenceIds);
		} else {
			$presences = Model_PresenceFactory::getPresences();
		}

		$currentDate = clone $startDate;
        $dateRangeString = (string)$dateRange;

        // get all badges, except for total (the total score is not saved)
        $badges = static::getBadges();
        unset($badges[Badge_Total::NAME]);

        $createRow = self::$db->prepare("INSERT INTO `badge_history` (`presence_id`, `daterange`, `date`) VALUES (:presence_id, :date_range, :date)");
        $emptyRow = array();
        foreach ($badges as $b) {
            $emptyRow[$b->getName()] = null;
            $emptyRow[$b->getName() . '_rank'] = null;
        }
		while ($currentDate <= $endDate) {
            $formattedDate = $currentDate->format('Y-m-d');
            $output->writeln("Checking $formattedDate");
			if (array_key_exists($formattedDate, $groupedData)) {
                $badgeScores = $groupedData[$formattedDate];
            } else {
                $badgeScores = array();
            }

            // only calculate the scores for missing presences, and only calculate ranks if something new has been calculated
            $missingCount = 0;
            $successCount = 0;
            foreach ($presences as $p) {
                $presenceId = $p->getId();
                // create the data in the database if it is missing
                if (array_key_exists($presenceId, $badgeScores)) {
                    $existing = (array)$badgeScores[$presenceId];
                } else {
                    $createRow->execute(array(
                        ':presence_id' => $presenceId,
                        ':date_range' => $dateRangeString,
                        ':date' => $formattedDate
                    ));

                    $existing = array_merge($emptyRow, array(
                        'id' => self::$db->lastInsertId(),
                        'presence_id' => $presenceId,
                        'date_range' => $dateRangeString,
                        'date' => $formattedDate
                    ));
                }
				$presenceUpdateClauses = array();
				$presenceUpdateArgs = array();
                foreach ($badges as $b) {
                    $badgeName = $b->getName();
					$missing = is_null($existing[$badgeName]);
					if ($missing) {
                        $missingCount++;
					}
                    if ($missing || $force) {
                        $score = $b->calculate($p, $currentDate, $dateRange);
                        if (!is_null($score)) {
							$presenceUpdateClauses[] = "`{$badgeName}` = :{$badgeName}";
							$presenceUpdateArgs[$badgeName] = $score;
                            $successCount++;
                        }
                    }
                }

				if ($presenceUpdateClauses) {
					$updates = implode(', ', $presenceUpdateClauses);
					$stmt = self::$db->prepare("UPDATE `badge_history` SET $updates WHERE `id` = :id");
					$presenceUpdateArgs['id'] = $existing['id'];
					$stmt->execute($presenceUpdateArgs);
				}
            }

			$output->writeln("Found $missingCount missing, updated $successCount");

            if ($successCount > 0) {
				$output->writeln("Assigning ranks");
                foreach ($badges as $b) {
                    $b->assignRanks($currentDate, $dateRange);
                }
            }
			$currentDate->modify('+1 day');
		}
	}

    /**
     * Gets all of the badge history data for the given presences, in the given date range, with the campaign(s) they belong to.
     * If no presence IDs are given, all presence data is returned
     * @param Enum_Period $dateRange
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param array $presenceIds
     * @param array $clauses
     * @param array $args
     * @return array|null
     */
    public static function getAllCurrentData(
		Enum_Period $dateRange,
		\DateTime $startDate,
		\DateTime $endDate,
		$presenceIds = array(), $clauses = array(), $args = array()
	) {
		$clauses = array_merge($clauses, array(
			'h.date >= :start_date',
			'h.date <= :end_date',
			'h.daterange = :date_range'
		));
		$args = array_merge($args, array(
			':start_date' => $startDate->format('Y-m-d'),
			':end_date' => $endDate->format('Y-m-d'),
			':date_range' => (string) $dateRange
		));
		if (count($presenceIds)) {
			$clauses[] = 'h.presence_id IN ('.implode(',', array_map('intval', $presenceIds)).')';
		}

		$sql = '
			SELECT
				h.*,
				c.id AS campaign_id,
				NULLIF(c.parent, 0) AS region_id
			FROM
				badge_history as h
				LEFT OUTER JOIN campaign_presences as cp ON h.presence_id = cp.presence_id
				LEFT OUTER JOIN campaigns AS c ON cp.campaign_id = c.id
			WHERE
				'.implode(' AND ', $clauses).'
			ORDER BY
				presence_id ASC,
				date DESC
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
                    return $type == Badge_Total::NAME ? null : $type . '_rank';
                }, $badgeNames));
		foreach ($data as $row) {
			foreach ($rankNames as $rank) {
                $row->$rank = intval($row->$rank);
			}
		}
		return $data;
	}

    /**
     * Gets the most recent badge data for all presences (or the given ones, if defined
     * @param bool $asArray
     * @param array $presenceIds
     * @return array|bool|mixed|null
     */
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
				$data = Badge_Factory::getAllCurrentData(Enum_Period::MONTH(), $startDate, $endDate);
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

	public static function setDB(Database $db)
	{
		self::$db = $db;
	}

    /**
     * @return Database
     */
    protected static function getDb()
	{
		if (is_null(self::$db)) {
			self::$db = Zend_Registry::get('db');
		}
		return self::$db;
	}
}