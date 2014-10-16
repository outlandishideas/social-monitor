<?php

require_once 'vendor/autoload.php';
require_once('MetricTest.php.base');

class ResponseTimeTest extends MetricTest
{
	protected function setUpMetric()
	{
		$this->metric = new Metric_ResponseTime();
	}
}