<?php

require_once 'vendor/autoload.php';

class BrandingTest extends PHPUnit_Framework_TestCase
{
	protected $metric;

	protected function setUp()
	{
		$this->metric = new Metric_Branding();
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
		$presence->method('getBranding')->willReturn($input);
		$this->assertEquals($expected, $this->metric->calculate($presence, new DateTime, new DateTime));
	}

	public function provider()
	{
		return array(
			array(0, 0),
			array(1, 1)
		);
	}
}