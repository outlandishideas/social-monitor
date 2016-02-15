<?php

namespace Outlandish\SocialMonitor\ObjectCache;

use Model_Country;
use Model_Group;
use Model_PresenceFactory;
use Model_Region;
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

    /**
     * @param TableIndex $table
     */
    public function setCountriesTable($table)
    {
        $this->countriesTable = $table;
    }

    /**
     * @param TableIndex $table
     */
    public function setGroupsTable($table)
    {
        $this->groupsTable = $table;
    }

    /**
     * @param TableIndex $table
     */
    public function setRegionsTable($table)
    {
        $this->regionsTable = $table;
    }

    /**
     * @param string $indexName
     * @param array $items
     * @param TableIndex $table
     */
    protected function updateIndexCache($indexName, $items, $table)
    {
        $rows = $table->getRows($items);
        $this->setObjectCache($indexName, $rows);
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
        $this->updateIndexCache('presence-index', Model_PresenceFactory::getPresences(), $this->presencesTable);
    }

    /**
     * Updates the country index cache
     */
    public function updateCountryIndexCache()
    {
        $this->updateIndexCache('country-index', Model_Country::fetchAll(), $this->countriesTable);
    }

    /**
     * Updates the group index cache
     */
    public function updateGroupIndexCache()
    {
        $this->updateIndexCache('group-index', Model_Group::fetchAll(), $this->groupsTable);
    }

    /**
     * Updates the region index cache
     */
    public function updateRegionIndexCache()
    {
        $this->updateIndexCache('region-index', Model_Region::fetchAll(), $this->regionsTable);
    }

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