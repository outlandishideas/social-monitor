<?php

require_once 'vendor/autoload.php';

class PeriodTest extends PHPUnit_Framework_TestCase
{
	public function setUp()
	{
		parent::setUp();
        defined('APPLICATION_PATH')
        || define('APPLICATION_PATH', __DIR__ . '/../../application');
        require_once 'Zend/Loader/Autoloader.php';
        $autoloader = Zend_Loader_Autoloader::getInstance();
        $resourceLoader = new Zend_Application_Module_Autoloader(array(
            'namespace' => '',
            'basePath'  => APPLICATION_PATH
        ));
        $resourceLoader->addResourceType('exception', 'exceptions/', 'Exception');
        $resourceLoader->addResourceType('util', 'util/', 'Util');
        $resourceLoader->addResourceType('refactored', 'models/refactored/', 'NewModel');
        $resourceLoader->addResourceType('metric', 'metrics/', 'Metric');
        $resourceLoader->addResourceType('badge', 'badges/', 'Badge');
	}

	/**
	 * @dataProvider provider
	 * @group badges
	 */
	public function testMonth($input, $expected)
	{
		$period = Badge_Period::MONTH();
		$this->assertEquals($expected['month'], $period->getBegin($input));
	}

	/**
	 * @dataProvider provider
	 * @group badges
	 */
	public function testWeek($input, $expected)
	{
		$period = Badge_Period::WEEK();
		$this->assertEquals($expected['week'], $period->getBegin($input));
	}

	public function provider()
	{
		return array(
			array(
				new DateTime('2014-10-17 00:00:00'),
				array('month' => new DateTime('2014-09-17 00:00:00'), 'week' => new DateTime('2014-10-10 00:00:00'))
			),
			array(
				new DateTime('2014-09-17 00:00:00'),
				array('month' => new DateTime('2014-08-17 00:00:00'), 'week' => new DateTime('2014-09-10 00:00:00'))
			),
			array(
				new DateTime('2014-12-31 00:00:00'),
				array('month' => new DateTime('2014-12-01 00:00:00'), 'week' => new DateTime('2014-12-24 00:00:00'))
			),
			array(
				new DateTime('2015-01-01 00:00:00'),
				array('month' => new DateTime('2014-12-01 00:00:00'), 'week' => new DateTime('2014-12-25 00:00:00'))
			),
			array(
				new DateTime('2015-03-01 00:00:00'),
				array('month' => new DateTime('2015-02-01 00:00:00'), 'week' => new DateTime('2015-02-22 00:00:00'))
			)
		);
	}
}