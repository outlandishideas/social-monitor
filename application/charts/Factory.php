<?php

abstract class Chart_Factory {

    protected static $charts = array();
    protected static $db = null;

    /**
     * return an array of the Chart Names
     * @return array
     */
    public static function getChartNames()
    {
        return array_keys(self::getCharts());
    }

    /**
     * method to get all chart singletons
     * @return Chart_Abstract[]
     */
    public static function getCharts()
    {
        if (empty(self::$charts)) {
            $db = self::getDb();
            /** @var Chart_Abstract[] $charts */
            $charts = array(
                new Chart_Compare($db),
                new Chart_Popularity($db),
                new Chart_PopularityTrend($db),
                new Chart_Reach($db),
                new Chart_Engagement($db),
                new Chart_Quality($db),
                new Chart_ActionsPerDay($db),
                new Chart_ResponseTime($db)
            );
            self::$charts = array();
            foreach ($charts as $c) {
                self::$charts[$c->getName()] = $c;
            }
        }
        return self::$charts;
    }

    /**
     * method to get a single chart singleton
     * @param string $name
     * @throws Exception
     * @return Chart_Abstract
     */
    public static function getChart($name)
    {
        $charts = self::getCharts();
        if (!array_key_exists($name, $charts)) {
            throw new Exception('Invalid chart name: ' . $name);
        }
        return $charts[$name];
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