<?php

namespace Outlandish\SocialMonitor\Validation;
abstract class BaseValidator
{
    private $errorMessage = "Value for %s is not valid";
    
    abstract public function isValid($candidate);

    public function getErrorMessage($formInput){
        return sprintf($this->errorMessage, $formInput);
    }
    
    public function setErrorMessage($message){
        $this->errorMessage = $message;
    }
}