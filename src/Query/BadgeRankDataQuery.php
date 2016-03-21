<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 07/05/2015
 * Time: 17:00
 */

namespace Outlandish\SocialMonitor\Query;


use Badge_Abstract;
use Badge_Factory;
use Badge_Total;
use DateTime;
use PDO;

class BadgeRankDataQuery
{
    /**
     * @var PDO
     */
    private $db;
    private $cache = [];

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->badges = Badge_Factory::getBadges();
    }

    public function getChartData(BadgeRankQuerier $model, Badge_Abstract $badge, DateTime $start, DateTime $end)
    {
        $data = $data = $this->getData($start, $end);

        if (!is_array($data)) {
            return [];
        }

        $groupColumn = $model->getColumn();

        $newData = [];
        foreach ($data as $row) {
            $id = $row->{$groupColumn};

            if ($id != $model->getId()) {
                continue;
            }

            if (!array_key_exists($row->date, $newData)) {
                $newData[$row->date] = ['score' => 0, 'rank' => 0, 'total' => 0];
            }

            $newData[$row->date]['score'] += $this->getScore($badge, $row);
            $newData[$row->date]['total']++;
        }

        foreach ($newData as &$row) {
            $row = $row['score'] / $row['total'];
        }

        return $newData;
    }

    private function getData(DateTime $start, DateTime $end)
    {
        $key = "{$start->format("Ymd")}-{$end->format("Ymd")}";
        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = Badge_Factory::getAllCurrentData(\Enum_Period::MONTH(), $start, $end);
        }

        return $this->cache[$key];
    }

    public function get(BadgeRankQuerier $model, Badge_Abstract $badge, DateTime $date)
    {
        $key = "{$date->format("Ymd")}-{$date->format("Ymd")}";
        if (!array_key_exists($key, $this->cache)) {

            $clauses = [
                'c.campaign_type IN ('.implode(',', array_map('intval', $model->getCampaignTypes())).')'
            ];

            $data = Badge_Factory::getAllCurrentData(\Enum_Period::MONTH(), $date, $date, [], $clauses);

            if ($data === null) {
                return null;
            }

            $newData = $this->getGroupedData($model, $data);

            foreach($this->badges as $b) {
                foreach ($newData[$b->getName()] as &$row) {
                    $row['score'] /= $row['total'];
                }
                Badge_Abstract::doRanking($newData[$b->getName()]);
            }

            $this->cache[$key] = $newData;
        }

        return $this->cache[$key][$badge->getName()][$model->getId()]['rank'];
    }

    /**
     * @param Badge_Abstract $badge
     * @param $row
     * @return mixed
     */
    protected function getScore(Badge_Abstract $badge, $row)
    {
        if ($badge->getName() == Badge_Total::getInstance()->getName()) {
            return ($row->reach + $row->engagement + $row->quality) / 3;
        } else {
            return $row->{$badge->getName()};
        }
    }

    /**
     * @param BadgeRankQuerier $model
     * @param $data
     * @return array
     */
    protected function getGroupedData(BadgeRankQuerier $model, $data)
    {
        $groupColumn = $model->getColumn();

        $newData = [];
        foreach ($data as $row) {
            $id = $row->{$groupColumn};

            foreach ($this->badges as $badge) {

                if (!array_key_exists($badge->getName(), $newData)) {
                    $newData[$badge->getName()] = [];
                }

                if (!array_key_exists($id, $newData[$badge->getName()])) {
                    $newData[$badge->getName()][$id] = ['score' => 0, 'rank' => 0, 'total' => 0];
                }

                $newData[$badge->getName()][$id]['score'] += $this->getScore($badge, $row);
                $newData[$badge->getName()][$id]['total']++;
            }


        }
        return $newData;
    }

}