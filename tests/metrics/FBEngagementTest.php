<?php

require_once 'vendor/autoload.php';
require_once('MetricTest.php.base');

class FBEngagementTest extends MetricTest
{
	protected function setUpMetric()
	{
		$this->metric = new Metric_FBEngagement();
	}

	/**
	 * @dataProvider provider
	 * @group metrics
	 */
	public function testCalculation($input, $expected)
	{
		$presence = $this->getMockBuilder('NewModel_Presence')
									->disableOriginalConstructor()
									->getMock()
		;
		$presence->method('getFacebookEngagement')->willReturn($input);
		$this->assertEquals($expected, $this->metric->calculate($presence, new DateTime, new DateTime));
	}

	public function provider()
	{
		return array(
			array(0, 0),
			array(1, 1),
			array(10.3, 10.3),
			array(51.6, 51.6),
			array(null, null)
		);
	}
}