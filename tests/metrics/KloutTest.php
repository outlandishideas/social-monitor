<?php

require_once 'vendor/autoload.php';
require_once('MetricTest.php.base');

class KloutTest extends MetricTest
{
	protected function setUpMetric()
	{
		$this->metric = new Metric_Klout();
	}
}