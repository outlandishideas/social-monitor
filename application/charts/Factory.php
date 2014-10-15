<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 14/10/2014
 * Time: 12:56
 */

abstract class Chart_Factory {

    protected static $charts = array();
    protected static $db = null;

    /**
     * @param $name
     * @return mixed
     */
    protected static function getClassName($name)
    {
        $classNames = self::getClassNames();
        return $classNames[$name];
    }

    /**
     * @return array
     */
    public static function getClassNames()
    {
        return array(
            Chart_Compare::getName() => 'Chart_Compare',
            Chart_Popularity::getName() => 'Chart_Popularity',
        );
    }

    /**
     * return an array of the Chart Names
     * @return array
     */
    public static function getChartNames()
    {
        $classNames = self::getClassNames();
        return array_keys($classNames);
    }

    /**
     * method to get all chart singletons
     * @return Chart_Abstract[]
     */
    public static function getCharts()
    {
        $charts = array();
        foreach(self::getChartNames() as $name){
            $charts[$name] = self::getChart($name);
        }
        return $charts;
    }

    /**
     * method to get a single chart singleton
     * @param mixed $name
     * @return Chart_Abstract
     */
    public static function getChart($name)
    {
        if (!array_key_exists($name, self::$charts)) {
            $className = static::getClassName($name);
            self::$charts[$name] = new $className(self::getDb());
        }
        return self::$charts[$name];
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