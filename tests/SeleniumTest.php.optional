<?php

class SeleniumTest extends PHPUnit_Extensions_SeleniumTestCase
{
	public static $seleneseDirectory = 'tests/selenese';

	public static $browsers = array(
      array(
        'name'    => 'Firefox on Linux',
        'browser' => '*firefox',
        'host'    => 'localhost',
        'port'    => 4444,
        'timeout' => 30000,
      ),
      array(
        'name'    => 'Chrome on Linux',
        'browser' => '*googlechrome',
        'host'    => 'localhost',
        'port'    => 4444,
        'timeout' => 30000,
      )
    );

    protected function setUp()
    {
        $this->setBrowserUrl('https://bcmonitor.beta.gd/');
    }
}