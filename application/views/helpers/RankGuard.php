<?php

class Zend_View_Helper_RankGuard extends Zend_View_Helper_Abstract
{
	/** @var \Outlandish\SocialMonitor\Services\Rank\Guard */
	private $guard;

	public function __construct() {
		$this->guard = Zend_Registry::get('rank_guard');
	}
	public function rankGuard() {
		return $this->guard;
	}

}