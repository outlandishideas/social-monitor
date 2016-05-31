<?php
namespace Outlandish\SocialMonitor\Validation;

class StringValidator extends BaseValidator
{
    protected $errorMessage = 'route.base.validation.string-forbidden';

    public function isValid($candidate){
        return preg_match('/^[\pL\pN@,.&()!#\/ _-]*$/u', $candidate);
    }
}