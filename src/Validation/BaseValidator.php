<?php

namespace Outlandish\SocialMonitor\Validation;
abstract class BaseValidator
{
    protected $errorMessage = "%s is not valid";
    
    abstract public function isValid($candidate);

    public function getErrorMessage($formInput="input"){
        return sprintf($this->errorMessage, $formInput);
    }

    public function setErrorMessage($message){
        $this->errorMessage = $message;
    }
}