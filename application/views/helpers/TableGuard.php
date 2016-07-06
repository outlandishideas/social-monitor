<?php

class Zend_View_Helper_TableGuard extends Zend_View_Helper_Abstract
{
	/** @var \Outlandish\SocialMonitor\Services\TableIndex\Guard */
	private $guard;

	public function __construct() {
		$this->guard = Zend_Registry::get('table_guard');
	}
	public function tableGuard() {
		return $this->guard;
	}

}