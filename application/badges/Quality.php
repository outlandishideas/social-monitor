<?php

class Badge_Quality extends Badge_Abstract
{
	protected static $name = 'reach';

	protected function getMetrics()
	{
		if (count($this->metrics) == 0) {
			$this->metrics = array(
				Metric_SignOff::getName()			=> 1,
				Metric_Relevance::getName()		=> 1,
				Metric_Branding::getName()			=> 1,
				Metric_ActionsPerDay::getName()	=> 1,
				Metric_Relevance::getName()		=> 1,
				Metric_LikesPerPost::getName()	=> 1
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