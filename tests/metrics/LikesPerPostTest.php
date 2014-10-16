<?php

require_once 'vendor/autoload.php';
require_once('MetricTest.php.base');

class LikesPerPostTest extends MetricTest
{
	protected function setUpMetric()
	{
		$this->metric = new Metric_LikesPerPost();
	}
}