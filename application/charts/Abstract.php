<?php

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
        $chartData = $this->getData($model, $start, $end);
        return array(
            "bindto" => '#new-chart',
            //this doesn't seem to work
            "line" => array(
                "connectNull" => true
            ),
            "data" => $chartData,
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

    static function getInstance() {
        return Chart_Factory::getChart(self::getName());
    }

}