<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 29/04/2015
 * Time: 17:56
 */

namespace Outlandish\SocialMonitor\ObjectCache;


use Badge_Factory;
use Outlandish\SocialMonitor\Query\TotalPopularityHistoryDataQuery;
use PDO;

class FanDataObjectCache {
    const FAN_DATA_30 = 'fan_data_30';

    /**
     * @var PDO
     */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @param bool $allowTemp
     * @param int $expires
     *
     * @return mixed|null
     */
    public function get($allowTemp = true, $expires = 86400)
    {
        $tableName = "object_cache";
        $key = self::FAN_DATA_30;
        $sql = "SELECT * FROM $tableName WHERE `key` = :key ORDER BY last_modified DESC LIMIT 1";
        $statement = $this->db->prepare($sql);
        $statement->execute([':key' => $key]);
        $result = $statement->fetch(PDO::FETCH_OBJ);
        if ($result) {
            if ((time() - strtotime($result->last_modified)) < $expires && ( $allowTemp || $result->temporary == 0)) {
                return json_decode(gzuncompress( $result->value));
            }
        }

        return null;
    }

    public function set($temp = false)
    {
        $key = self::FAN_DATA_30;
        $now = new \DateTime();
        $old = clone $now;
        $old->modify('-30 days');

        $fanData = ['b' => array_fill_keys(Badge_Factory::getBadgeNames(), [])];
        $popularityData = (new TotalPopularityHistoryDataQuery($this->db))->get($old, $now);
        foreach ($popularityData as $row) {

            foreach ($fanData['b'] as $badge => &$days) {
                if (!array_key_exists($row['date'], $days)) {
                    $days[$row['date']] = ['s' => 0];
                }
                $days[$row['date']]['s'] += $row['score'];
            }
        }

        foreach($fanData['b'] as $badge => &$days) {
            $days = array_values($days);
            $days = array_reverse($days);
            $currentDay = 30;
            $newDays = [];
            foreach($days as $day => $score) {
                $newDays[$currentDay] = $score;
                $currentDay--;
            }
            $days = $newDays;
        }

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
        $insert->execute(array(':key' => $key, ':value' => gzcompress(json_encode($fanData)), ':temp' => $temp ? 1 : 0));

        return $fanData;
    }

}