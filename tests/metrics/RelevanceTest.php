<?php

require_once 'vendor/autoload.php';
require_once('MetricTest.php.base');

class RelevanceTest extends MetricTest
{
	protected function setUpMetric()
	{
		$this->metric = new Metric_Relevance();
	}

	/**
	 * @dataProvider historicStreamMetaData
	 * @group metrics
	 */
	public function testCalculation($input, $expected)
	{
		$presence = $this->getMockBuilder('NewModel_Presence')
									->disableOriginalConstructor()
									->getMock()
		;
		$presence->method('getHistoricStreamMeta')->willReturn($input);
		$this->assertEquals($expected['relevance'], $this->metric->calculate($presence, new DateTime, new DateTime));
	}
}