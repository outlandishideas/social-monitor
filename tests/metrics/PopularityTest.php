<?php

require_once 'vendor/autoload.php';
require_once('MetricTest.php.base');

class PopularityTest extends MetricTest
{
	protected function setUpMetric()
	{
		$this->metric = new Metric_Popularity();
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
		$presence->method('getPopularity')->willReturn($input);
		$this->assertEquals($expected, $this->metric->calculate($presence, new DateTime, new DateTime));
	}

	public function provider()
	{
		return array(
			array(0, null),
			array(1, 1),
			array(103, 103),
			array(516, 516),
			array(null, null)
		);
	}
}