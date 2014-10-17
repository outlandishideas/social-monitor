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

    public function getChart($model, DateTime $start, DateTime $end)
    {
        return array(
            "bindto" => '#new-chart',
            //this doesn't seem to work
            "line" => array(
                "connect_null" => true,
                "connectNull" => true
            ),
            "data" => $this->getData($model, $start, $end),
            "axis" => array(
                "x" => $this->getXAxis(),
                "y" => $this->getYAxis()
            )
        );
    }

    abstract protected function getData($model, DateTime $start, DateTime $end);

    /**
     * @return mixed
     */
    public static function getTitle()
    {
        return static::$title;
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

    protected function getYLabel()
    {
        return $this->yLabel;
    }

    protected function getXLabel()
    {
        return $this->xLabel;
    }

    /**
     * Return parameters for the x axis
     * See c3.js documentation for what to return
     * @return array
     */
    abstract protected function getXAxis();

    /**
     * Return parameters for the y axis
     * See c3.js documentation for what to return
     * @return mixed
     */
    abstract protected function getYAxis();

    /**
     * Get the public names for each dataset from $data
     * @param $data
     * @return array // eg. array("internal_name" => "external_name", "internal_name_2" => "external_name_2")
     */
    abstract protected function getNames($data = null);

    /**
     * Get columns chart from $data
     * @param $data
     * @return array // eg. array(array("internal_name", value1, value2, etc.), array("x_axis", tick1, tick2)
     */
    abstract protected function getColumns($data = null);


}