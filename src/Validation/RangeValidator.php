<?php

namespace Outlandish\SocialMonitor\Validation;

class RangeValidator extends BaseValidator
{
    protected $errorMessage = 'route.base.validation.not-in-range';
    private $min;
    private $max;

    public function __construct($min, $max)
    {
        $this->min = $min;
        $this->max = $max;
    }

    public function isValid($candidate){
        if ($candidate < $this->min || $candidate > $this->max){
            return false;
        }

        return true;
    }

    public function getErrorMessage($formInput='input')
    {
        return parent::$translator->trans($this->errorMessage,
            ['%min%' => $this->min, '%max%' => $this->max, '%label%' => $formInput]);
    }
}
