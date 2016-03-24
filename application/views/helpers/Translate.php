<?php

class Zend_View_Helper_Translate extends Zend_View_Helper_Abstract {

    /** @var \Symfony\Component\Translation\Translator */
    private $translate;

    public function __construct() {
        $this->translate = Zend_Registry::get('symfony_translate');
    }

    public function translate() {
        return $this->translate;
    }

}
