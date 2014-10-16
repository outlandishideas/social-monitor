<?php

require_once 'vendor/autoload.php';
require_once('MetricTest.php.base');

class PopularityTimeTest extends MetricTest
{
	protected function setUpMetric()
	{
		$this->metric = new Metric_PopularityTime();
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
		$presence->method('getTargetAudienceDate')->willReturn($input);
		$this->assertEquals($expected, $this->metric->calculate($presence, new DateTime, new DateTime));
	}

	public function provider()
	{
		$ret = array();
		foreach(array(0, 2, 3, 5, 7, 11, 13, 17, 19, 23, 29, 31, 37, 41, 43, 100, 500, 1000) as $m)
		{
			$dt = new DateTime("now +$m months");
			$dt->setTime(0,0,0);
			$ret[] = array($dt, $m);
		}
		$ret[] = array(null, null);
		return $ret;
	}
}