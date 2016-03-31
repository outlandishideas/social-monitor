<?php

abstract class Chart_Abstract {

    protected $xLabel;
    protected $yLabel;
	protected $name;
	protected $title;
	protected $description;
	/** @var \Symfony\Component\Translation\Translator */
	protected $translate;

    protected function __construct(PDO $db, $translator, $name)
    {
        if (is_null($db)) {
            $db = Zend_Registry::get('db')->getConnection();
        }
        $this->name = $name;
        $this->db = $db;
		$this->translate = $translator;
		$this->title = $this->translate->trans('chart.' . $name . '.title');
		$this->description = $this->translate->trans('chart.' . $name . '.description');

		$xAxisKey = 'chart.' . $name . '.x-axis-label';
		$xAxis = $this->translate->trans($xAxisKey);
		if ($xAxis != $xAxisKey) {
			$this->xLabel = $xAxis;
		}

		$yAxisKey = 'chart.' . $name . '.y-axis-label';
		$yAxis = $this->translate->trans($yAxisKey);
		if ($yAxis != $yAxisKey) {
			$this->yLabel = $yAxis;
		}
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
    public function getTitle()
    {
        return $this->title;
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
    public function getName()
    {
        return $this->name;
    }

    /**
     * Return parameters for the x axis
     * See c3.js documentation for what to return
     * @return array
     */
	protected function getXAxis()
	{
		return array(
			"type" => 'timeseries',
			"label" => $this->xLabel,
			"position" => 'outer-center'
		);
	}


	/**
     * Return parameters for the y axis
     * See c3.js documentation for what to return
     * @return mixed
     */
	protected function getYAxis()
	{
		return array(
			"label" => $this->yLabel,
			"position" => 'outer-middle',
		);
	}


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

}