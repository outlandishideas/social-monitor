<?php

namespace Outlandish\SocialMonitor\Validation;

class RangeValidator extends BaseValidator
{
    protected $errorMessage = "Input for %s is not a valid number.";
    private $min = null;
    private $max = null;

    public function __construct($min, $max)
    {
        $this->min = $min;
        $this->max = $max;
    }

    public function isValid($candidate){
        if ($candidate < $this->min || $candidate > $this->max){
            $this->setErrorMessage("Input for %s must be between $this->min than $this->max");
            return false;
        }

        return true;
    }
}
