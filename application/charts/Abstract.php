<?php

abstract class Chart_Abstract {

    protected static $title;
    protected static $name;

    protected $xLabel;
    protected $yLabel;
    protected $description;

    public function __construct(PDO $db = null)
    {
        if (is_null($db)) {
            $db = Zend_Registry::get('db')->getConnection();
        }
        $this->db = $db;
		// populate $name, $title, $description from transation files
		$translate = Zend_Registry::get('translate');
		$className = get_class($this);
		$this->name = $translate->_($className.'.name');
		$this->title = $translate->_($className.'.title');
		$this->description = $translate->_($className.'.description');
    }

    public function getChart($model, DateTime $start, DateTime $end)
    {
        $chartData = $this->getData($model, $start, $end);
        return array(
            'description' => $this->getDescription(),
            'chartArgs' => array(
                "bindto" => '#new-chart',
                //this doesn't seem to work
                "line" => array(
                    "connectNull" => true
                ),
                "data" => $chartData,
                "axis" => array(
                    "x" => $this->getXAxis(),
                    "y" => $this->getYAxis()
                ),
                "tooltip" => $this->getTooltip(),
                "legend" => $this->getLegend()
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
    public function getDescription()
    {
        return $this->description;
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

    protected function getTooltip() {
        return array(
            'show' => false
        );
    }

    protected function getLegend() {
        return array(
            'show' => false
        );
    }

    static function getInstance() {
        return Chart_Factory::getChart(self::getName());
    }

}