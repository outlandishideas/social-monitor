<?php
namespace Outlandish\SocialMonitor\Validation;

class StringValidator extends BaseValidator
{
    protected $errorMessage = "%s contains forbidden characters";

    public function isValid($candidate){
        return preg_match('/^[^><\/\"]*$/', $candidate);
    }
}