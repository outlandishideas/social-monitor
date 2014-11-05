<?php

require_once 'vendor/autoload.php';
require_once('MetricTest.php.base');

class LikesPerPostTest extends MetricTest
{
	protected $presence;

	protected function setUpMetric()
	{
		$this->metric = new Metric_LikesPerPost();

		$this->presence = $this->getMockBuilder('Model_Presence')
									->disableOriginalConstructor()
									->getMock()
		;
	}

	/**
	 * @group sina_weibo
	 * @group metrics
	 */
	public function testSinaWeiboReturnsNull()
	{
		$this->presence->method('getType')->willReturn(Enum_PresenceType::SINA_WEIBO());
		$this->assertNull($this->metric->calculate($this->presence, new DateTime, new DateTime));
	}

	/**
	 * @group twitter
	 * @group metrics
	 */
	public function testTwitterReturnsNull()
	{
		$this->presence->method('getType')->willReturn(Enum_PresenceType::TWITTER());
		$this->assertNull($this->metric->calculate($this->presence, new DateTime, new DateTime));
	}

	/**
	 * @dataProvider provider
	 * @group facebook
	 * @group metrics
	 */
	public function testCalulations($input, $expected)
	{
		$this->presence->method('getType')->willReturn(Enum_PresenceType::FACEBOOK());
		$this->presence->method('getHistoricStream')->willReturn($input);
		$this->assertEquals($expected, $this->metric->calculate($this->presence, new DateTime, new DateTime));
	}

	public function provider()
	{
		return array(
			array(
				array(
					array('likes' => 0),
					array('likes' => 0),
					array('likes' => 0),
					array('likes' => 0),
					array('likes' => 0)
				),
				0
			),
			array(
				array(
					array('likes' => 3),
					array('likes' => 4),
					array('likes' => 5),
					array('likes' => 6),
					array('likes' => 7)
				),
				5
			),
			array(
				array(),
				null
			)
		);
	}
}