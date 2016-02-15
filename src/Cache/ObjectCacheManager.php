<?php

namespace Outlandish\SocialMonitor\Cache;

use Badge_Factory;
use Enum_Period;
use Model_Base;
use Model_Country;
use Model_Group;
use Model_PresenceFactory;
use Model_Region;
use Outlandish\SocialMonitor\Query\TotalPopularityHistoryDataQuery;
use Outlandish\SocialMonitor\TableIndex\TableIndex;
use PDO;
use Zend_Db_Adapter_Pdo_Abstract;
use Zend_Registry;

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

    /** @var Zend_Db_Adapter_Pdo_Abstract */
    protected $db;


    function __construct()
    {
        $this->db = Zend_Registry::get('db');
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

    public function getFrontPageData($dayRange, $temp)
    {
        $badgeData = $this->getBadgeData($dayRange, $temp);
        $mapData = $this->getMapData($badgeData, $dayRange, $temp);
        $smallCountryData = $this->getSmallCountryData($mapData, $dayRange, $temp);
        $groupData = $this->getGroupData($badgeData, $dayRange, $temp);
        $fanData = $this->getFanData($dayRange, $temp);
        return [$mapData, $smallCountryData, $groupData, $fanData];
    }

    public function getBadgeData($dayRange, $temp)
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
        return $badgeData;
    }

    public function populatePresenceBadgeData()
    {
        $key = 'presence_badges';
        $oldData = $this->getObjectCache($key, false);
        if(!$oldData) {
            //if no oldData (too old or temp) get current data (which is now up to date) and set it in the object cache
            $data = Badge_Factory::getAllCurrentData(Enum_Period::MONTH(), new \DateTime(), new \DateTime());
            $this->setObjectCache($key, $data, false);
        }
    }

    public function getMapData($badgeData, $dayRange, $temp)
    {
        $allBadgeTypes = Badge_Factory::getBadgeNames();

        $key = 'map_data_' . $dayRange;
        $mapData = $this->getObjectCache($key, $temp);

        if (!$mapData) {
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

            $this->setObjectCache($key, $mapData, $temp);
        }

        return $mapData;
    }

    public function getSmallCountryData($mapData, $dayRange, $temp)
    {
        $key = 'small_country_data_' . $dayRange;
        $smallMapData = $this->getObjectCache($key, $temp);
        if (!$smallMapData) {
            $smallCountries = Model_Country::smallCountryCodes();
            $smallMapData = array();
            foreach($mapData as $country){
                if(array_key_exists($country->c, $smallCountries)){
                    $smallMapData[] = $country;
                }
            }
            $this->setObjectCache($key, $smallMapData, $temp);
        }
        return $smallMapData;
    }

    public function getGroupData($badgeData, $dayRange, $temp)
    {
        $key = 'group_data_' . $dayRange;
        $groupData = $this->getObjectCache($key, $temp);
        if (!$groupData) {
            $groupData = Model_Group::constructFrontPageData($badgeData, $dayRange);
            $this->setObjectCache($key, $groupData, $temp);
        }
        return $groupData;
    }

    public function getFanData($dayRange, $temp)
    {
        $key = 'fan_data_' . $dayRange;
        $fanData = $this->getObjectCache($key, $temp);
        if (!$fanData) {
            $now = new \DateTime();
            $old = clone $now;
            $old->modify('-30 days')->setTime(0, 0, 0);

            $dateData = array();
            $query = new TotalPopularityHistoryDataQuery($this->db->getConnection());
            $popularityData = $query->get($old, $now);
            foreach ($popularityData as $row) {
                $diff = $old->diff(new \DateTime($row['date']));
                $dayOffset = $diff->days;
                $dateData[$dayOffset] = array('s' => intval($row['score']));
            }
            ksort($dateData);

            $fanData = array('b' => array());
            foreach (Badge_Factory::getBadgeNames() as $badgeName) {
                $fanData['b'][$badgeName] = $dateData;
            };

            $this->setObjectCache($key, $fanData, $temp);
        }
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
        $result = $statement->fetch(PDO::FETCH_OBJ);
        if ($result) {
            if ((time() - strtotime($result->last_modified)) < $expires && ( $allowTemp || $result->temporary == 0)) {
                return json_decode(gzuncompress( $result->value));
            }
        }
        return false;
    }
}