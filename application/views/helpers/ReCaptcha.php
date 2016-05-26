<?php

class Zend_View_Helper_ReCaptcha extends Zend_View_Helper_Abstract {

    /** @var \Outlandish\SocialMonitor\Helper\ReCaptcha */
    private $recaptcha;

    public function __construct() {
        $this->recaptcha = Zend_Registry::get('recaptcha');
    }

    public function recaptcha() {
        return $this->recaptcha;
    }

}
