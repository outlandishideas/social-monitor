<?php
namespace Outlandish\SocialMonitor\Validation;

class StringValidator extends BaseValidator
{
    private $errorMessage = "Value for %s is not a valid string.";

    public function isValid($candidate){
        return preg_match('/^[a-zA-Z-_. ]*$/', $candidate);
    }
}