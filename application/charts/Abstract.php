<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 14/10/2014
 * Time: 12:56
 */

abstract class Chart_Abstract {

    protected static $title;
    protected static $description;
    protected static $name;

    protected $xLabel;
    protected $yLabel;

    public function __construct(PDO $db = null)
    {
        if (is_null($db)) {
            $db = Zend_Registry::get('db')->getConnection();
        }
        $this->db = $db;
    }

    abstract public function getData(NewModel_Presence $presence, DateTime $start, DateTime $end);

    /**
     * @return mixed
     */
    public static function getTitle()
    {
        return self::$title;
    }

    /**
     * @return mixed
     */
    public static function getDescription()
    {
        return static::$description;
    }

    /**
     * @return mixed
     */
    public static function getName()
    {
        return static::$name;
    }

    public function getYLabel()
    {
        return $this->yLabel;
    }

    public function getXLabel()
    {
        return $this->xLabel;
    }


}