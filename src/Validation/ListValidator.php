<?php
/**
 * Created by PhpStorm.
 * User: Jannik
 * Date: 20.05.16
 * Time: 15:40
 */

namespace Outlandish\SocialMonitor\Validation;


class ListValidator extends BaseValidator
{
    private $listElements = [];
    protected $errorMessage = "%s is not in the list of valid arguments";

    public function __construct($listElements)
    {
        $this->listElements = $listElements;
    }

    public function isValid($candidate){
        return in_array($candidate, $this->listElements);
    }
}

