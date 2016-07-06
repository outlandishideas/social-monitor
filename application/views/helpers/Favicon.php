<?php

class Zend_View_Helper_Favicon extends Zend_View_Helper_Abstract {

    /** @var \Outlandish\SocialMonitor\Helper\Favicon */
    private $favicon;

    public function __construct() {
        $this->favicon = Zend_Registry::get('favicon');
    }

    public function favicon() {
        return $this->favicon;
    }

}
