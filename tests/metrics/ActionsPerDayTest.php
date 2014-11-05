<?php

require_once 'vendor/autoload.php';
require_once('MetricTest.php.base');

class ActionsPerDayTest extends MetricTest
{
	protected function setUpMetric()
	{
		$this->metric = new Metric_ActionsPerDay();
	}

	/**
	 * @dataProvider historicStreamMetaData
	 * @group metrics
	 */
	public function testCalculation($input, $expected)
	{
		$presence = $this->getMockBuilder('Model_Presence')
									->disableOriginalConstructor()
									->getMock()
		;
		$presence->method('getHistoricStreamMeta')->willReturn($input);
		$this->assertEquals($expected['actions'], $this->metric->calculate($presence, new DateTime, new DateTime));
	}
}