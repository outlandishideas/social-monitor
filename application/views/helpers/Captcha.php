<?php

class Zend_View_Helper_Captcha extends Zend_View_Helper_Abstract {

    /** @var \Outlandish\SocialMonitor\Helper\ReCaptcha */
    private $recaptcha;

    public function __construct() {
        $this->recaptcha = Zend_Registry::get('recaptcha');
    }

    public function captcha() {
        return $this->recaptcha;
    }

}
