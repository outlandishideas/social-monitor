<?php

namespace Outlandish\SocialMonitor\Validation;
abstract class BaseValidator
{
    protected $errorMessage = "%s is not valid";
    protected static $translator;

    abstract public function isValid($candidate);

    public static function setTranslator($translator){
        self::$translator = $translator;
    }

    public function getErrorMessage($formInput="input"){
        return self::$translator->trans($this->errorMessage, ['%label%' => $formInput]);
    }

    public function setErrorMessage($message){
        $this->errorMessage = $message;
    }
}