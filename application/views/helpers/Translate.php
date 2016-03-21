<?php

class Zend_View_Helper_Translate extends Zend_View_Helper_Abstract {

    /** @var Zend_Translate */
    private $translate;

    public function __construct() {
        $this->translate = Zend_Registry::get('translate');
    }

    public function translate($key) {
        return $this->translate->_($key);
    }

}
