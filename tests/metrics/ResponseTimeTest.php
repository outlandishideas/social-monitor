<?php

require_once 'vendor/autoload.php';
require_once('MetricTest.php.base');

class ResponseTimeTest extends MetricTest
{
	protected function setUpMetric()
	{
		$this->metric = new Metric_ResponseTime();
	}

	/**
	 * @dataProvider provider
	 * @group metrics
	 */
	public function testCalculation($input, $expected)
	{
		$presence = $this->getMockBuilder('Model_Presence')
									->disableOriginalConstructor()
									->getMock()
		;
		$presence->method('getResponseData')->willReturn($input);
		$this->assertEquals($expected, $this->metric->calculate($presence, new DateTime, new DateTime));
	}

	public function provider()
	{
		return array(
			array(
				array(
					(object) array('diff' => 0),
					(object) array('diff' => 0),
					(object) array('diff' => 0),
					(object) array('diff' => 0),
					(object) array('diff' => 0)
				),
				0
			),
			array(
				array(
					(object) array('diff' => 3),
					(object) array('diff' => 4),
					(object) array('diff' => 5),
					(object) array('diff' => 6),
					(object) array('diff' => 7)
				),
				5
			),
			array(
				array(),
				null
			),
		);
	}
}