<?php

class TestTest extends PhpUnit_Framework_TestCase {
	public function testTest() {
		$a = 1 + 1;
		$this->assertEquals(2, $a);
	}
}