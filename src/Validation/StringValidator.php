<?php
namespace Outlandish\SocialMonitor\Validation;

class StringValidator extends BaseValidator
{
    protected $errorMessage = "Value for %s is not a valid string.";

    public function isValid($candidate){
        return preg_match('/^[a-zA-Z-_. äöü]*$/', $candidate);
    }
}