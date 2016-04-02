<?php

namespace Outlandish\SocialMonitor\Cache;

use Badge_Factory;
use Enum_Period;
use Model_Country;
use Model_Group;
use Outlandish\SocialMonitor\Database\Database;
use Outlandish\SocialMonitor\Query\TotalPopularityHistoryDataQuery;
use Outlandish\SocialMonitor\TableIndex\TableIndex;

class ObjectCacheManager
{
    /** @var TableIndex */
    protected $presencesTable;
    /** @var TableIndex */
    protected $countriesTable;
    /** @var TableIndex */
    protected $groupsTable;
    /** @var TableIndex */
    protected $regionsTable;

    /** @var Database */
    protected $db;

    protected $popularityQuery;

    function __construct(Database $db, TotalPopularityHistoryDataQuery $querier)
    {
        $this->db = $db;
        $this->popularityQuery = $querier;
    }

    /**
     * @param TableIndex $table
     */
    public function setPresencesTable($table)
    {
        $this->presencesTable = $table;
    }

    public function getPresencesTable()
    {
        return $this->presencesTable;
    }

    /**
     * @param TableIndex $table
     */
    public function setCountriesTable($table)
    {
        $this->countriesTable = $table;
    }

    public function getCountriesTable()
    {
        return $this->countriesTable;
    }

    /**
     * @param TableIndex $table
     */
    public function setGroupsTable($table)
    {
        $this->groupsTable = $table;
    }

    public function getGroupsTable()
    {
        return $this->groupsTable;
    }

    /**
     * @param TableIndex $table
     */
    public function setRegionsTable($table)
    {
        $this->regionsTable = $table;
    }

    public function getRegionsTable()
    {
        return $this->regionsTable;
    }

    /**
     * Gets the rows for the given table from the object cache, generating them from the items if
     * necessary (when cache is missing or when forced)
     * @param TableIndex $table
     * @param bool $force
     * @return array
     */
    protected function getTableIndex($table, $force = false)
    {
        $indexName = $table->getIndexName();

        $rows = array();
        if (!$force) {
            $rows = $this->getObjectCache($indexName);
        }

        if (!$rows) {
            $rows = $table->generateRows();
            $this->setObjectCache($indexName, $rows);
        }

        return $rows;
    }

    /**
     * Updates the presence index cache
     *
     * The presence index table is very large and was performing very badly. This method calculates the needed
     * values for this table and stores it as json in the object cache table to be used when rendering that
     * particular page.
     */
    public function updatePresenceIndexCache()
    {
        return $this->getPresenceIndexRows(true);
    }

    public function getPresenceIndexRows($force = false)
    {
        return $this->getTableIndex($this->presencesTable, $force);
    }

    /**
     * Updates the country index cache
     */
    public function updateCountryIndexCache()
    {
        return $this->getCountryIndexRows(true);
    }

    public function getCountryIndexRows($force = false)
    {
        return $this->getTableIndex($this->countriesTable, $force);
    }

    /**
     * Updates the group index cache
     */
    public function updateGroupIndexCache()
    {
        return $this->getGroupIndexRows(true);
    }

    public function getGroupIndexRows($force = false)
    {
        return $this->getTableIndex($this->groupsTable, $force);
    }

    /**
     * Updates the region index cache
     */
    public function updateRegionIndexCache()
    {
        return $this->getRegionIndexRows(true);
    }

    public function getRegionIndexRows($force = false)
    {
        return $this->getTableIndex($this->regionsTable, $force);
    }

    /**
     * Forces an update of the xxx_data_xxx object caches
     * @param int $dayRange
     */
    public function updateFrontPageData($dayRange = 30)
    {
        //todo include week data in the data that we send out as json
        $badgeData = Badge_Factory::getAllCurrentData(
            Enum_Period::MONTH(),
            new \DateTime("now -$dayRange days"),
            new \DateTime('now')
        );
        $this->setObjectCache('badge_data_' . $dayRange, $badgeData, false);

        $mapData = $this->calculateMapData($badgeData, $dayRange);
        $this->setObjectCache('map_data_' . $dayRange, $mapData, false);

        $groupData = Model_Group::constructFrontPageData($badgeData, $dayRange);
        $this->setObjectCache('group_data_' . $dayRange, $groupData, false);

        $fanData = $this->calculateFanData($dayRange);
        $this->setObjectCache('fan_data_' . $dayRange, $fanData, false);
    }

    public function getFrontPageData($dayRange, $temp)
    {
        $key = 'badge_data_' . $dayRange;
        $badgeData = $this->getObjectCache($key, $temp);
        if (!$badgeData) {
            //todo include week data in the data that we send out as json
            $badgeData = Badge_Factory::getAllCurrentData(
                Enum_Period::MONTH(),
                new \DateTime("now -$dayRange days"),
                new \DateTime('now')
            );
            $this->setObjectCache($key, $badgeData, $temp);
        }

        $key = 'map_data_' . $dayRange;
        $mapData = $this->getObjectCache($key, $temp);
        if (!$mapData) {
            $mapData = $this->calculateMapData($badgeData, $dayRange);
            $this->setObjectCache($key, $mapData, $temp);
        }

        $key = 'group_data_' . $dayRange;
        $groupData = $this->getObjectCache($key, $temp);
        if (!$groupData) {
            $groupData = Model_Group::constructFrontPageData($badgeData, $dayRange);
            $this->setObjectCache($key, $groupData, $temp);
        }

        $key = 'fan_data_' . $dayRange;
        $fanData = $this->getObjectCache($key, $temp);
        if (!$fanData) {
            $fanData = $this->calculateFanData($dayRange);
            $this->setObjectCache($key, $fanData, $temp);
        }

        return [$mapData, $groupData, $fanData];
    }

    public function populatePresenceBadgeData()
    {
        $data = Badge_Factory::getAllCurrentData(Enum_Period::MONTH(), new \DateTime(), new \DateTime());
        $this->setObjectCache('presence_badges', $data, false);
    }

    public function calculateMapData($badgeData, $dayRange)
    {
        $allBadgeTypes = Badge_Factory::getBadgeNames();

        $mapData = Model_Country::constructFrontPageData($badgeData, $dayRange);

        $existingCountries = array();
        foreach($mapData as $country){
            $existingCountries[$country->c] = $country->n;
        }

        // construct a set of data for a country that has no presence
        $blankBadges = new \stdClass();
        foreach ($allBadgeTypes as $badgeType) {
            $blankBadges->{$badgeType} = array();
            for ($day = 1; $day <= $dayRange; $day++) {
                $blankBadges->{$badgeType}[$day] = (object)array('s'=>0,'l'=>'N/A');
            }
        }

        $missingCountries = array_diff_key(Model_Country::countryCodes(), $existingCountries);
        foreach($missingCountries as $code => $name){
            $mapData[] = (object)array(
                'id'=>-1,
                'c' => $code,
                'n' => $name,
                'p' => 0,
                'b' => $blankBadges
            );
        }

        return $mapData;
    }

    public function calculateFanData($dayRange)
    {
        $now = new \DateTime();
        $old = clone $now;
        $old->modify('-30 days')->setTime(0, 0, 0);

        $dateData = array();
        $popularityData = $this->popularityQuery->get($old, $now);
        foreach ($popularityData as $row) {
            $diff = $old->diff(new \DateTime($row['date']));
            $dayOffset = $diff->days;
            $dateData[$dayOffset] = array('s' => intval($row['score']));
        }

        // fill in any gaps, by copying the closest one
        for ($day = 0; $day <= $dayRange; $day++) {
            if (!array_key_exists($day, $dateData)) {
                $key = '';
                for ($offset = 1; $offset < 30; $offset++) {
                    if (array_key_exists($day - $offset, $dateData)) {
                        $key = $day - $offset;
                        break;
                    } else if (array_key_exists($day + $offset, $dateData)) {
                        $key = $day + $offset;
                        break;
                    }
                }
                if ($key) {
                    $dateData[$day] = $dateData[$key];
                }
            }
        }

        ksort($dateData);

        $fanData = array('b' => array());
        foreach (Badge_Factory::getBadgeNames() as $badgeName) {
            $fanData['b'][$badgeName] = $dateData;
        };

        return $fanData;
    }

    /**
     * Adds an object to the object cache
     * @param string $key
     * @param object|array $value
     * @param bool $temp
     */
    public function setObjectCache($key, $value, $temp = false)
    {
        // delete any old/temporary entries for this key
        $deleteSql = 'DELETE FROM object_cache WHERE `key` = :key';
        $deleteArgs = array(':key' => $key);
        if ($temp) {
            $deleteSql .= ' AND `temporary` = :temp';
            $deleteArgs[':temp'] = 1;
        }
        $delete = $this->db->prepare($deleteSql);
        $delete->execute($deleteArgs);

        $insert = $this->db->prepare('INSERT INTO object_cache (`key`, value, `temporary`) VALUES (:key, :value, :temp)');
        $insert->execute(array(':key' => $key, ':value' => gzcompress(json_encode($value)), ':temp' => $temp ? 1 : 0));
    }

    /**
     * Gets an object from the object cache. Will ignore temporary or old objects if the arguments won't allow them
     * @param string $key
     * @param bool $allowTemp
     * @param int $expires
     * @return object|false
     */
    public function getObjectCache($key, $allowTemp = true, $expires = 86400)
    {
        $sql = 'SELECT * FROM object_cache WHERE `key` = :key ORDER BY last_modified DESC LIMIT 1';
        $statement = $this->db->prepare($sql);
        $statement->execute(array(':key' => $key));
        $result = $statement->fetch(\PDO::FETCH_OBJ);
        if ($result) {
            if ((time() - strtotime($result->last_modified)) < $expires && ( $allowTemp || $result->temporary == 0)) {
                return json_decode(gzuncompress( $result->value));
            }
        }
        return false;
    }

	/**
	 * Invalidates the object cache for the given key
	 * @param string $key
	 * @return bool
	 */
	public function invalidateObjectCache($key)
	{
		$sql = 'DELETE FROM object_cache WHERE `key` = :key';
		$statement = $this->db->prepare($sql);
		$statement->execute(array(':key' => $key));
		return $statement->execute();
	}
}