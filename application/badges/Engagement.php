<?php

class Badge_Engagement extends Badge_Abstract
{
	protected static $name = 'engagement';

	protected function getMetrics()
	{
		if (count($this->metrics) == 0) {
			$this->metrics = array(
				Metric_Klout::getName()				=> 1,
				Metric_FBEngagement::getName()	=> 1,
				Metric_ResponseTime::getName() 	=> 1,
				Metric_ResponseRatio::getName() 	=> 1
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