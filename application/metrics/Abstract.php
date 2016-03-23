<?php

abstract class Metric_Abstract {

    protected $name;
    protected $title;
    protected $icon;
    protected $gliding;
    public $target = 0;

    /**
     * run the actual calculations
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return mixed
     */
    abstract public function calculate(Model_Presence $presence, \DateTime $start, \DateTime $end);

    /**
     * Get the metric-score for a given presence for a given daterange
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int The score between 0 and 100
     */
    abstract public function getScore(Model_Presence $presence, \DateTime $start, \DateTime $end);

    /**
     * Gets the data that is used to calculate the score. Produces into a named array so it can be used for other purposes
     *
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return array
     */
    abstract public function getData(Model_Presence $presence, \DateTime $start, \DateTime $end);

	public function __construct($name, $icon, $gliding = true)
	{
		$translate = Zend_Registry::get('translate');
		$className = get_class($this);

		$this->name = $name;
		$this->title = $translate->_($className.'.title');
		$this->icon = $icon;
		$this->gliding = $gliding;
	}

	public function getName()
    {
        return $this->name;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getIcon()
    {
        return $this->icon;
    }

    protected static function boundScore($score, $min = 0, $max = 100) {
        return max($min, min($max, $score));
    }

    public function isGliding() {
        return $this->gliding;
    }
}