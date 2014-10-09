<?php

class Badge_Reach extends Badge_Abstract
{
	protected $name = 'reach';

	protected function doCalculation($data)
	{
		var_dump($data);
	}

	protected function getMetrics()
	{
		if (count($this->metrics) == 0) {
			$this->metrics = array(
				Metric_Popularity::getName()		=> 1,
				Metric_PopularityTime::getName()	=> 1
			);
			foreach ($this->metrics as $name => $weight) {
            //get weight from database, if it exists
	        $weighting = BaseController::getOption($name . '_weighting');
	        if ($weighting > 0) {
	            $this->metrics[$name] = $weighting;
	        }
        }
		}
		return $this->metrics;
	}
}