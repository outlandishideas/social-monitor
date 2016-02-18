<?php

class Zend_View_Helper_Gatekeeper extends Zend_View_Helper_Abstract
{
	public function gatekeeper()
	{
		return new \Outlandish\SocialMonitor\Helper\Gatekeeper($this->view);
	}

}
